<?php
/**
 * Output TXT file for OMEGA stock movement import from TradeGecko
 */

require dirname(__FILE__).'/config.php';

$header = array(
    'Content-type: application/json',
    'Authorization: Bearer '.TG_PRIVILIGED_CODE,
);

$get_no = isset($_GET['no']) ? $_GET['no'] : '';
$get_days = isset($_GET['days']) ? (int) $_GET['days'] : 1;
$start = date('Y-m-d', strtotime("-$get_days days"));
$filter = '?created_at_min='.$start;
if (!empty($get_no)) {
    $filter = '?limit=1&term='.urlencode($get_no);
}

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, TG_API_URL."/purchase_orders{$filter}");
curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
$purchase_orders = json_decode(curl_exec($ch));
curl_close($ch);

if (empty($purchase_orders->purchase_orders[0])) {
    echo "No orders";
    return;
}

$output = array();
foreach ($purchase_orders->purchase_orders as $order) {
    if ($order->default_price_list_id != 'buy') {
        continue;
    }

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, TG_API_URL.'/addresses/'.$order->supplier_address_id);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    $supplier_address = json_decode(curl_exec($ch));
    $supplier_address = $supplier_address->address;
    curl_close($ch);

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, TG_API_URL.'/companies/'.$order->company_id);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    $company = json_decode(curl_exec($ch));
    $company = $company->company;
    curl_close($ch);

    switch ($supplier_address->country) {
        case 'Česká Republika':
            $currency = 'CZK';
            break;
        case 'Hungary':
            $currency = 'HUF';
            break;
        default:
            $currency = 'EUR';
    }

    $subtype_of_movement = 1;
    if (!in_array($supplier_address->country, array('SK', 'SVK'))) {
        $subtype_of_movement = 2;
    }

    $r02_items = array();
    foreach ($order->purchase_order_line_item_ids as $purchaes_order_line_item_id) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, TG_API_URL.'/purchase_order_line_items/'.$purchaes_order_line_item_id);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $purchase_order_line_item = json_decode(curl_exec($ch));
        curl_close($ch);

        $item = $purchase_order_line_item->purchase_order_line_item;
        $r02_items[] = array(
            'R02',
            'name_of_card' => $item->label,
            'quantity' => $item->quantity,
            'unit_price' => $item->price,
            'card_number' => '',
            'unit_incidental_cost' => '',
            'unit_price_foreign_currency' => '',
            'catalog_price' => '',
            'discount' => '',
            'unit_of_measurement' => '',
            'quantity_of_measurement' => '',
            'external_card_number' => '',
            'rounding' => -1,
            'rate_of_VAT' => 0,
            'rounding_VAT' => -1,
            'unit_price_with_VAT' => '',
            'price_given_including_VAT' => '',
            'catalog_price_with_VAT' => '',
            'unit_discount_without_VAT' => '',
            'unit_discount_with_VAT' => '',
            'code_of_center' => '',
            'name_of_center' => $supplier_address->label,
            'code_of_order' => $order->order_number,
            'name_of_order' => $order->order_number,
            'code_of_operation' => '',
            'name_of_operation' => '',
            'code_of_worker' => '',
            'name_of_worker' => 'TradeGecko',
            'surname_of_worker' => '',
        );
    }

    $r01 = array(
        'R01',
        'store_name' => 'Sklad1',
        'number_of_evidence' => '',
        'type_of_movement' => 'P', // Prijem - income
        'subtype_of_movement' => $subtype_of_movement,
        'partner_company_name' => $company->name,
        'partner_company_number' => $company->company_code,
        'date_of_issue' => date('d.m.Y H:i:s', strtotime($order->created_at)),
        'tally_code' => 'PV',
        'sequence_code' => '',
        'internal_number_of_partner' => '',
        'code_of_partner' => '',
        'partner_center' => $supplier_address->label,
        'partner_branch' => '',
        'street' => $supplier_address->address1,
        'postal_code' => $supplier_address->zip_code,
        'city' => $supplier_address->city,
        'delivery_note_number' => '',
        'invoice_number' => $order->order_number,
        'signed_by' => 'EMPLOYEE_NAME',
        'currency' => $currency,
        'incidental_costs' => '',
        'exchange_rate' => '',
        'costs' => '',
        'other_costs' => '',
        'incidental_costs2' => '',
        'quantity_of_unit' => '',
        'exchange_rate2' => '',
        'order_system' => 0,
        'empty_export_import' => '',
        'recycle_fund' => 0,
        'reg_order_number' => '',
        'type_of_accounting' => 'PT',
        'rounding' => -1,
        'vat_calc_method' => 0,
        'old_vat_calc_method' => 0,
        'date_of_issue2' => '',
    );

    $r01 =  iconv('UTF-8', 'WINDOWS-1250', implode("\t", $r01));

    $r02 = '';
    foreach ($r02_items as $item) {
        $r02 .=  iconv('UTF-8', 'WINDOWS-1250', implode("\t", $item))."\r\n";
    }

    $output[] = "$r01\r\n$r02";
}

$txt_file = 'tg-omega-stock-movement.txt';
header('Content-Disposition: attachment; filename='.$txt_file);
echo "R00\tT02\r\n" . implode("\r\n", $output);
