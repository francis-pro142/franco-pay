<?php
namespace App\Services;

use App\Services\DatabaseConnection;
use App\Models\Wallet;
use App\Models\TransactionModel;
use App\Models\Audit;

class TransactionService
{
    public static function createTransaction(int $userId, string $recipientWalletNumber, float $amount, ?string $idempotencyKey = null, ?string $description = null): array
    {
        $pdo = DatabaseConnection::get();
        $driver = $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);

        // Load sender wallet
        $senderWallet = Wallet::findByUserId($userId);
        if (!$senderWallet) {
            return ['status' => 'FAILED', 'reason' => 'SENDER_WALLET_NOT_FOUND'];
        }

        // Idempotency check
        if ($idempotencyKey) {
            $existing = TransactionModel::findByIdempotency($idempotencyKey, (int)$senderWallet['id']);
            if ($existing) {
                return ['status' => $existing['status'], 'transaction' => $existing];
            }
        }

        // Load receiver wallet
        $recipientWalletNumber = trim((string)$recipientWalletNumber);
        $receiverWallet = Wallet::findByWalletNumber($recipientWalletNumber);
        if (!$receiverWallet) {
            return ['status' => 'FAILED', 'reason' => 'RECIPIENT_NOT_FOUND'];
        }

        if ((int)$senderWallet['id'] === (int)$receiverWallet['id']) {
            return ['status' => 'FAILED', 'reason' => 'SELF_TRANSFER_BLOCKED'];
        }

        if ($amount <= 0) {
            return ['status' => 'FAILED', 'reason' => 'INVALID_AMOUNT'];
        }

        if ($amount > (float)$senderWallet['balance']) {
            return ['status' => 'FAILED', 'reason' => 'INSUFFICIENT_FUNDS'];
        }

        try {
            // Begin transaction
            if ($driver === 'sqlite') {
                $pdo->beginTransaction();
            } else {
                $pdo->beginTransaction();
            }

            // Ensure up-to-date balances and attempt debit atomically
            if ($driver === 'sqlite') {
                // For SQLite, do a select then update within the transaction
                $stmt = $pdo->prepare('SELECT balance FROM wallets WHERE id = ?');
                $stmt->execute([(int)$senderWallet['id']]);
                $row = $stmt->fetch(\PDO::FETCH_ASSOC);
                $balance = (float)$row['balance'];
                if ($balance < $amount) {
                    // record failed transaction
                    $ref = 'TXN-' . date('Ymd') . '-' . bin2hex(random_bytes(4));
                    $txId = TransactionModel::insert([
                        'transaction_reference' => $ref,
                        'idempotency_key' => $idempotencyKey,
                        'sender_wallet_id' => (int)$senderWallet['id'],
                        'receiver_wallet_id' => (int)$receiverWallet['id'],
                        'amount' => $amount,
                        'status' => 'FAILED',
                        'description' => $description,
                    ]);
                    Audit::log($userId, 'TRANSACTION_FAILED', 'transaction', $txId, null, ['reason' => 'INSUFFICIENT_FUNDS']);
                    $pdo->commit();
                    return ['status' => 'FAILED', 'reason' => 'INSUFFICIENT_FUNDS'];
                }

                // perform debit
                $stmt = $pdo->prepare('UPDATE wallets SET balance = balance - ? WHERE id = ?');
                $stmt->execute([$amount, (int)$senderWallet['id']]);

                // perform credit
                $stmt = $pdo->prepare('UPDATE wallets SET balance = balance + ? WHERE id = ?');
                $stmt->execute([$amount, (int)$receiverWallet['id']]);
            } else {
                // MySQL path - use SELECT ... FOR UPDATE
                $stmt = $pdo->prepare('SELECT balance FROM wallets WHERE id = ? FOR UPDATE');
                $stmt->execute([(int)$senderWallet['id']]);
                $row = $stmt->fetch(\PDO::FETCH_ASSOC);
                $balance = (float)$row['balance'];
                if ($balance < $amount) {
                    $ref = 'TXN-' . date('Ymd') . '-' . bin2hex(random_bytes(4));
                    $txId = TransactionModel::insert([
                        'transaction_reference' => $ref,
                        'idempotency_key' => $idempotencyKey,
                        'sender_wallet_id' => (int)$senderWallet['id'],
                        'receiver_wallet_id' => (int)$receiverWallet['id'],
                        'amount' => $amount,
                        'status' => 'FAILED',
                        'description' => $description,
                    ]);
                    Audit::log($userId, 'TRANSACTION_FAILED', 'transaction', $txId, null, ['reason' => 'INSUFFICIENT_FUNDS']);
                    $pdo->commit();
                    return ['status' => 'FAILED', 'reason' => 'INSUFFICIENT_FUNDS'];
                }

                $stmt = $pdo->prepare('UPDATE wallets SET balance = balance - ? WHERE id = ?');
                $stmt->execute([$amount, (int)$senderWallet['id']]);

                $stmt = $pdo->prepare('UPDATE wallets SET balance = balance + ? WHERE id = ?');
                $stmt->execute([$amount, (int)$receiverWallet['id']]);
            }

            // create transaction record
            $ref = 'TXN-' . date('Ymd') . '-' . bin2hex(random_bytes(4));
            $txId = TransactionModel::insert([
                'transaction_reference' => $ref,
                'idempotency_key' => $idempotencyKey,
                'sender_wallet_id' => (int)$senderWallet['id'],
                'receiver_wallet_id' => (int)$receiverWallet['id'],
                'amount' => $amount,
                'status' => 'SUCCESS',
                'description' => $description,
            ]);

            // ledger entries
            $stmt = $pdo->prepare('SELECT balance FROM wallets WHERE id = ?');
            $stmt->execute([(int)$senderWallet['id']]);
            $afterSender = (float)$stmt->fetch(\PDO::FETCH_ASSOC)['balance'];

            $stmt->execute([(int)$receiverWallet['id']]);
            $afterReceiver = (float)$stmt->fetch(\PDO::FETCH_ASSOC)['balance'];

            // Insert ledger for sender (DEBIT)
            $stmtInsert = $pdo->prepare('INSERT INTO ledger_entries (transaction_id, wallet_id, entry_type, amount, balance_before, balance_after) VALUES (?, ?, ?, ?, ?, ?)');
            $stmtInsert->execute([$txId, (int)$senderWallet['id'], 'DEBIT', $amount, $afterSender + $amount, $afterSender]);

            // Insert ledger for receiver (CREDIT)
            $stmtInsert->execute([$txId, (int)$receiverWallet['id'], 'CREDIT', $amount, $afterReceiver - $amount, $afterReceiver]);

            Audit::log($userId, 'TRANSACTION_SUCCESS', 'transaction', $txId, null, ['amount' => $amount, 'to' => $receiverWallet['wallet_number']]);

            $pdo->commit();

            $transaction = TransactionModel::findById($txId);
            return ['status' => 'SUCCESS', 'transaction' => $transaction];
        } catch (\Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            Audit::log($userId, 'TRANSACTION_FAILED', 'transaction', null, null, ['error' => $e->getMessage()]);
            return ['status' => 'FAILED', 'reason' => 'ERROR', 'message' => $e->getMessage()];
        }
    }
}
