// File: /js/libs/raydium-sdk.js
// Description: Wrapper for Raydium SDK to handle token swaps
// Created by: Vina Network

import { Connection, PublicKey, Transaction } from '/js/libs/solana.web3.iife.js';
import { Raydium, TxVersion } from '/vendor/node_modules/@raydium-io/raydium-sdk-v2/dist/index.esm.mjs';
import Decimal from '/vendor/node_modules/decimal.js/decimal.js';
import BN from '/vendor/node_modules/bn.js/lib/bn.js';

// Initialize Raydium SDK
async function initializeRaydium(rpcEndpoint) {
    try {
        const connection = new Connection(rpcEndpoint, 'confirmed');
        const raydium = await Raydium.load({
            connection,
            owner: null, // Will be set later with user public key
        });
        console.log('Raydium SDK initialized');
        return { raydium, connection };
    } catch (err) {
        console.error('Failed to initialize Raydium SDK:', err.message);
        throw new Error(`Raydium initialization failed: ${err.message}`);
    }
}

// Create swap transaction using Raydium SDK
async function createSwapTransaction({ tokenMint, solMint, amount, direction, publicKey, rpcEndpoint, slippage = 0.5 }) {
    try {
        const { raydium, connection } = await initializeRaydium(rpcEndpoint);
        const owner = new PublicKey(publicKey);
        const inputMint = direction === 'buy' ? new PublicKey(solMint) : new PublicKey(tokenMint);
        const outputMint = direction === 'buy' ? new PublicKey(tokenMint) : new PublicKey(solMint);
        const amountIn = new Decimal(amount).toString();
        const slippagePercent = new Decimal(slippage).toNumber(); // Raydium expects slippage as a decimal (e.g., 0.5 for 0.5%)

        // Fetch pool info
        const poolKeys = await raydium.liquidity.getPoolKeys({
            baseMint: inputMint,
            quoteMint: outputMint,
        });

        if (!poolKeys) {
            throw new Error(`No liquidity pool found for ${inputMint.toBase58()}/${outputMint.toBase58()}`);
        }

        // Create swap instruction
        const { innerTransaction } = await raydium.swap.createSwap({
            poolKeys,
            inputMint,
            outputMint,
            amountIn,
            slippage: slippagePercent,
            owner,
            direction: direction === 'buy' ? 'Base2Quote' : 'Quote2Base',
            txVersion: TxVersion.V0,
        });

        // Create transaction
        const transaction = new Transaction();
        transaction.add(...innerTransaction.instructions);
        transaction.feePayer = owner;
        transaction.recentBlockhash = (await connection.getLatestBlockhash()).blockhash;

        // Serialize transaction
        const serializedTx = transaction.serialize({ requireAllSignatures: false }).toString('base64');
        console.log(`Raydium swap transaction created: ${serializedTx.substring(0, 20)}...`);
        return serializedTx;
    } catch (err) {
        console.error(`Failed to create Raydium swap transaction: ${err.message}`);
        throw new Error(`Raydium swap transaction failed: ${err.message}`);
    }
}

export default {
    createSwapTransaction,
};
