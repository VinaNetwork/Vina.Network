<?php
// ============================================================================
// File: make-market/src/swap.php
// Description: Jupiter Swap API handler
// Created by: Vina Network
// ============================================================================

namespace VinaNetwork;

use Solana\Web3\Connection;
use Solana\Web3\Keypair;
use Solana\Web3\VersionedTransaction;
use GuzzleHttp\Client;

class JupiterSwap {
    private $connection;
    private $httpClient;
    private $keypair;
    private $transactionStatus;

    public function __construct($rpcEndpoint, $walletPrivateKey, TransactionStatus $transactionStatus) {
        $this->connection = new Connection($rpcEndpoint);
        $this->keypair = Keypair::fromSecretKey(base64_decode($walletPrivateKey));
        $this->httpClient = new Client();
        $this->transactionStatus = $transactionStatus;
    }

    public function waitForConfirmation($txSig, $processId, $action, $round, $maxAttempts = 30, $interval = 1, $maxRetries = 3) {
        for ($retry = 1; $retry <= $maxRetries; $retry++) {
            for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
                $status = $this->connection->getSignatureStatuses([$txSig]);
                $confirmationStatus = $status['value'][0]['confirmationStatus'] ?? null;
                $err = $status['value'][0]['err'] ?? null;

                if ($err) {
                    $errorMessage = json_encode($err);
                    if (strpos($errorMessage, 'insufficient liquidity') !== false) {
                        return ['confirmed' => false, 'error' => 'Không đủ thanh khoản trong pool'];
                    }
                    if ($retry < $maxRetries) {
                        return ['confirmed' => false, 'error' => "Thử lại lần $retry/$maxRetries: $errorMessage", 'retry' => true];
                    }
                    return ['confirmed' => false, 'error' => "Giao dịch thất bại sau $maxRetries lần thử: $errorMessage"];
                }
                if ($confirmationStatus === 'confirmed' || $confirmationStatus === 'finalized') {
                    return ['confirmed' => true, 'error' => null];
                }
                sleep($interval);
            }
        }
        return ['confirmed' => false, 'error' => 'Hết thời gian chờ xác nhận giao dịch'];
    }

    public function getQuote($inputMint, $outputMint, $amount, $slippageBps) {
        try {
            $response = $this->httpClient->get('https://quote-api.jup.ag/v6/quote', [
                'query' => [
                    'inputMint' => $inputMint,
                    'outputMint' => $outputMint,
                    'amount' => $amount,
                    'slippageBps' => $slippageBps
                ]
            ]);
            return json_decode($response->getBody(), true);
        } catch (\Exception $e) {
            return ['error' => 'Không lấy được route từ Jupiter: ' . $e->getMessage()];
        }
    }

    public function executeSwap($quoteResponse, $processId, $action, $round) {
        try {
            $this->transactionStatus->sendStatus($processId, "Đang thực hiện $action vòng $round...");
            $response = $this->httpClient->post('https://quote-api.jup.ag/v6/swap', [
                'json' => [
                    'userPublicKey' => $this->keypair->publicKey()->toString(),
                    'quoteResponse' => $quoteResponse,
                    'wrapAndUnwrapSol' => true
                ]
            ]);
            $swapData = json_decode($response->getBody(), true);
            $transaction = VersionedTransaction::deserialize(base64_decode($swapData['swapTransaction']));
            $transaction->sign([$this->keypair]);
            $txSig = $this->connection->sendRawTransaction($transaction->serialize());
            $this->transactionStatus->sendStatus($processId, "Đang chờ xác nhận $action vòng $round...");
            return ['txSig' => $txSig, 'error' => null];
        } catch (\Exception $e) {
            $this->transactionStatus->sendStatus($processId, "Lỗi $action vòng $round: " . $e->getMessage());
            return ['txSig' => null, 'error' => 'Lỗi khi tạo/gửi giao dịch: ' . $e->getMessage()];
        }
    }

    public function getBalance() {
        return $this->connection->getBalance($this->keypair->publicKey());
    }

    public function getTokenAccounts($tokenMint) {
        return $this->connection->getTokenAccountsByOwner($this->keypair->publicKey(), ['mint' => $tokenMint]);
    }
}
