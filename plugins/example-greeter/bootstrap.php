<?php
// Hooks receive $name and $data.
if ($name === 'order.created') {
    error_log('Example plugin: order created '.($data['order_id'] ?? 'unknown'));
}
