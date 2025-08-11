<?php

namespace Examples\Client;

require __DIR__ . '/ModelExample.php';

// List with filters, sorting, includes
$purchases = Purchase::query()
    ->where('status', 'approved')            // filter[status]=approved
    ->where('created_at', '>=2024-01-01')    // filter[created_at]=>=2024-01-01
    ->orderBy('created_at', 'desc')          // sort=-created_at
    ->with(['user', 'items'])                // include=user,items
    ->paginate(10);                          // page/per_page

// Single record with includes
$purchase = Purchase::with(['user','items'])->find(123);

// Lazy loading
$purchase->loadMissing('items');

// Create via API
$created = Purchase::create(['name' => 'New']);

// Update via API
if ($purchase) {
    $purchase->update(['name' => 'Updated']);
}


