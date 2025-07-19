<?php
// ============================================================================
// File: make-market/websocket-server.php
// Description: WebSocket server for real-time transaction status updates
// Created by: Vina Network
// ============================================================================

use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;
use Ratchet\Server\IoServer;
use Ratchet\Http\HttpServer;
use Ratchet\WebSocket\WsServer;
require_once './vendor/autoload.php'; // Cập nhật đường dẫn

class TransactionStatus implements MessageComponentInterface {
    protected $clients;
    protected $processStatuses;

    public function __construct() {
        $this->clients = new \SplObjectStorage;
        $this->processStatuses = [];
    }

    public function onOpen(ConnectionInterface $conn) {
        $this->clients->attach($conn);
        echo "New connection! ({$conn->resourceId})\n";
    }

    public function onMessage(ConnectionInterface $from, $msg) {
        $data = json_decode($msg, true);
        if (isset($data['processId'])) {
            $this->processStatuses[$from->resourceId] = $data['processId'];
            echo "Client {$from->resourceId} subscribed to process {$data['processId']}\n";
        }
    }

    public function onClose(ConnectionInterface $conn) {
        $this->clients->detach($conn);
        unset($this->processStatuses[$conn->resourceId]);
        echo "Connection closed! ({$conn->resourceId})\n";
    }

    public function onError(ConnectionInterface $conn, \Exception $e) {
        echo "Error: {$e->getMessage()}\n";
        $conn->close();
    }

    public static function sendStatus($processId, $status) {
        $server = new self();
        foreach ($server->clients as $client) {
            if (isset($server->processStatuses[$client->resourceId]) && $server->processStatuses[$client->resourceId] === $processId) {
                $client->send(json_encode(['status' => $status]));
            }
        }
    }
}

// Chạy WebSocket server
$server = IoServer::factory(
    new HttpServer(
        new WsServer(
            new TransactionStatus()
        )
    ),
    8080
);
$server->run();
