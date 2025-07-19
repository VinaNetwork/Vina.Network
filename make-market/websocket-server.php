<?php
// ============================================================================
// File: make-market/websocket-server.php
// Description: WebSocket server for real-time transaction status updates
// Created by: Vina Network
// ============================================================================

use Ratchet\Server\IoServer;
use Ratchet\Http\HttpServer;
use Ratchet\WebSocket\WsServer;
use VinaNetwork\TransactionStatus;
require_once './vendor/autoload.php';

$server = IoServer::factory(
    new HttpServer(
        new WsServer(
            new TransactionStatus()
        )
    ),
    8080
);
$server->run();
