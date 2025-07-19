<?php
// ============================================================================
// File: make-market/src/status.php
// Description: WebSocket status handler
// Created by: Vina Network
// ============================================================================

namespace VinaNetwork;

use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;

class TransactionStatus implements MessageComponentInterface {
    protected $clients;
    protected $processStatuses;

    public function __construct() {
        $this->clients = new \SplObjectStorage;
        $this->processStatuses = [];
    }

    public function onOpen(ConnectionInterface $conn) {
        $this->clients->attach($conn);
    }

    public function onMessage(ConnectionInterface $from, $msg) {
        $data = json_decode($msg, true);
        if (isset($data['processId'])) {
            $this->processStatuses[$from->resourceId] = $data['processId'];
        }
    }

    public function onClose(ConnectionInterface $conn) {
        $this->clients->detach($conn);
        unset($this->processStatuses[$conn->resourceId]);
    }

    public function onError(ConnectionInterface $conn, \Exception $e) {
        $conn->close();
    }

    public function sendStatus($processId, $status) {
        foreach ($this->clients as $client) {
            if (isset($this->processStatuses[$client->resourceId]) && $this->processStatuses[$client->resourceId] === $processId) {
                $client->send(json_encode(['status' => $status]));
            }
        }
    }
}
