<?php
/**
 * Exports TradeGecko orders into Omega importable invoices
 */

require dirname(__FILE__).'/config.php';
error_reporting(-1);

$get_days = isset($_GET['days']) ? (int) $_GET['days'] : 1;

$query = array(
    'limit' => 500,
    'packed_status' => 'packed',
    'status' => 'finalized',
    'created_at_min' => date('Y-m-d\TH:i:s.u\Z', strtotime('-'.$get_days.' days')),
);
$orders = apiCall('/orders/?'.http_build_query($query));

if (empty($orders['orders'][0])) {
    echo 'No orders from date '.date('d.m.Y', strtotime('-'.$get_days.' days'));

    return;
}

$txt = array();
foreach ($orders['orders'] as $order) {
    $r02 = array();

    $line_items = apiCall("/order_line_items/?order_id={$order['id']}");
    if (!isset($line_items['order_line_items'])) {
        continue;
    }
    foreach ($line_items['order_line_items'] as $line_item) {
        $product = array(
            'name' => '',
        );
        if ($line_item['variant_id'] > 0) {
            $product = apiCall("/variants/{$line_item['variant_id']}");
            $product = reset($product);
        }

        if (empty($product['name'])) {
            continue;
        }

        $sku = !empty($product['sku']) ? $product['sku'] : '';

        $row = array(
            'R02',
            'nazov polozky - name of item' => $product['name'],
            'mnozstvo - quantity of item' => $line_item['quantity'],
            'MJ - unit' => 'ks',
            'jedn. cena bez DPH - unit price without VAT' => number_format($line_item['price'], 2, '.', ''),
            'sadzba DPH -rate of VAT' => 'V',
            'skladova cena - price in-store' => 0,
            'cennikova cena - list price' => number_format($line_item['price'] / $line_item['quantity'], 2, '.', ''),
            'percento zlava - percent discount' => 0,
            'typ polozky - type of item  ' => 'K',
            'cudzi nazov - foreign name' => $sku,
            'EAN' => '',
            'PLU' => '',
            'S ucet - synthetic account' => '604',
            'A ucet - analytic account' => '000',
            'colny sadzobnik - tariff' => '',
            'JKPOV' => '',
            'cislo karty/sluzby - item/service number' => $sku,
            'volna polozka - free item' => '',
            'nazov skladu - name of store' => '',
            'kod stredisko - code of center' => '',
            'nazov stredisko - name of center' => 'WAREHOUSE_NAME_HERE',
            'kod zakazka -code of order' => '',
            'nazov zakazka - name of order' => '',
            'kod cinnost - code of operation' => '',
            'nazov cinnost - name of operation' => '',
            'kod pracovnik - code of worker' => 1,
            'meno pracovnik - name of worker' => 'FIRST_NAME_HERE',
            'priezvisko pracovnik - surname of worker' => 'LAST_NAME_HERE',
            'typ DPH - type of VAT' => '03',
            'Pripravene - ready' => 0,
            'Dodane - delivered' => 0,
            'Vybavene - furnished' => 0,
            'PripraveneMR - ready from last year' => 0,
            'DodaneMR - delivered in last year' => 0,
            'Rezervovane - reserved' => 0,
            'RezervovaneMR - reserved from last year' => 0,
            'MJ odvodena - derived unit' => 'ks',
            'Mnozstvo z odvodenej MJ -quantity of derived unit' => $line_item['quantity'],
            'cislo stredisko - center number' => '',
            'cislo zakazka - order number' => '',
            'cislo cinnost - operation number' => '',
            'cislo pracovnik - worker number ' => '',
            'ExtCisloPolozky - item Ext' => '',
            'Zaokruhlenie - round' => -3,
            'Spôsob zaokruhlenia - round mode' => 3,
            'bola vybavena rucne - manually furnished' => 0,
            'nazov zlavy - name of discount' => '',
            'cennikova cena s DPH - list price with VAT' => number_format($line_item['price'] * 1.2, 2, '.', ''),
            'ceny boli zadavane s DPH - prices was entered with VAT' => 0,
            'jedn. cena s DPH - unit price with VAT' => number_format(($line_item['price'] * 1.2)/$line_item['quantity'], 2, '.', ''),
            'zlava v EUR bez DPH - discount in EUR without VAT' => 0,
            'zlava v EUR s DPH - discount in EUR with VAT' => 0,
            'Oddiel KVDPH' => '',
            'Druh tovaru KVDPH' => '',
            'Kod tovaru KVDPH' => '',
            'MJ pre KVDH' => '',
            'Mnozstvo KVDPH' => 0,
        );
        $r02[] = implode("\t", $row);
    }

    $company = array(
        'name' => '',
        'company_code' => '',
        'tax_number' => '',
    );
    if ($order['company_id'] > 0) {
        $company = apiCall('/companies/'.$order['company_id']);
        $company = reset($company);
    }

    $billing_address = apiCall('/addresses/'.$order['billing_address_id']);
    $billing_address = reset($billing_address);
    $shipping_address = apiCall('/addresses/'.$order['shipping_address_id']);
    $shipping_address = reset($shipping_address);
    $name = !empty($billing_address['company_name']) ? $billing_address['company_name'] : "{$billing_address['first_name']} {$billing_address['last_name']}";
    $country = !empty(trim($billing_address['country'])) ? $billing_address['country'] : 'SLOVENSKO';
    $order_date = strtotime($order['created_at']);

    $payment = 'Dobierka';
    $shipping_type = 'Kurier';

    $row = array(
        'R01',
        'cislo dokladu - receipt number' => '', // Leave blank to be autogenerated
        'meno partnera - partner name' => $name,
        'ICO -  REG' => $company['company_code'],
        'datum vystavenia/datum prijatia' => date('d.m.Y', $order_date),
        'datum splatnosti - due date' => date('d.m.Y', strtotime('+2 weeks', $order_date)),
        'DUZP' => date('d.m.Y', $order_date),
        'Zaklad Nizsia - VAT basis in lower VAT' => 0,
        'Zaklad Vyssia - VAT basis in higher VAT' => number_format($order['total'] / 1.2, 2, '.', ''),
        'Zaklad 0 - VAT basis in null VAT' => 0,
        'Zaklad Neobsahuje - basis in VAT free' => 0,
        'Sadzba Nizsia - TAX rate lower' => 10,
        'Sadzba Vyssia - TAX rate higher' => 20,
        'Suma DPH nizsia - Amount VAT lower' => 0,
        'Suma DPH vyssia - Amount VAT higher' => number_format($order['total'] - ($order['total'] / 1.2), 2, '.', ''),
        'Halierove vyrovnanie - Price correction' => 0,
        'Suma spolu CM - Amount in all in foreign currency' => number_format($order['total'], 2, '.', ''),
        'typ dokladu - type of receipts' => 0,
        'kod Ev - tally code' => 'OF',
        'kod CR - code of sequence' => 'OF',
        'interne cislo partnera - internal partner number' => '',
        'kod partnera - code of partner' => '',
        'stredisko - centre partner' => '',
        'prevadzka -plent partner' => '',
        'ulica - street' => $billing_address['address1'],
        'PSC - postal code' => $billing_address['zip_code'],
        'mesto - city' => $billing_address['city'],
        'DIC/DU - TAX partner' => $company['tax_number'],
        'cas vystavenia - time of issue' => date('H:i:s'),
        'dod. Podmienky - terms of delivery and payments' => '',
        'uvod - introduction' => '',
        'zaver -completion, ending' => 'Recyklačné poplatky sú zahrnuté v cene produktov.',
        'dod. List - bill of delivery' => '',
        'cislo objednavky - order number' => $order['order_number'],
        'vystavil - signed by' => 'FIRST_NAME_HERE LAST_NAME_HERE',
        'KS - constant symbol' => '0008',
        'SS - specific symbol' => '',
        'forma uhrady - payment' => $payment,
        'sposob dopravy - shipment' => $shipping_type,
        'Mena - currency' => 'EUR',
        'Mnozstvo jednotky - quantity of unit currency' => 1,
        'Kurz - exchange rate' => 1,
        'Suma spolu TM - amount in all - domestic currency' => number_format($order['total'], 2, '.', ''),
        'Zakazkovy list - bill of custom-made' => '',
        'poznamka -comment' => trim(preg_replace('/\s+/', ' ', $order['notes'])),
        'predmet fakturacie - subject of invoicing' => '',
        'partner stat - partner country' => $country,
        'Kod IC DPH - code of VAT' => '',
        'IC DPH - VAT' => $company['tax_number'],
        'Dodavatel cislo uctu - suppliers number of bank account' => 'BANK_ACC_HERE',
        'Dodavatel banka - suppliers name of bank' => 'BANK_NAME_HERE',
        'Dodavatel pobocka - suppliers branch of bank' => '',
        'partner stat - partner country2' => $country,
        'Kod vystavil - code of signed by' => 1,
        'Partner meno skratka - short name of partner' => substr($name, 0, 15),
        'Dodavatel  SWIFT - SWIFT of suppliers' => 'SWIFT_HERE',
        'Dodavatel IBAN - IBAN of suppliers' => 'IBAN_HERE',
        'Dodavatel kod statu DPH - code country in VAT of suppliers' => 'SK',
        'Dodavatel IC pre DPH - VAT of suppliers' => 'VAT_ID_HERE',
        'Dodavatel stat - country of suppliers' => '',
        'Zaokruhlenie - round' => -2,
        'Sposob zaokruhlenia - round mode' => 3,
        'IČO poradové číslo -  REG order number' => '',
        'Zaokruhlenie položky - round of item' => -4,
        'Sprievodny text k preddavku - Accompanying text to advance' => '',
        'Suma preddavku - amount of advance' => 0,
        'Spôsob výpočtu DPH - VAT calculation method' => 0,
        'Starý spôsob výpočtu DPH' => 0,
        'Datum vystavenia DF' => '',
        'Úhradené cez ECR - paid via ECR' => 0,
        'VS ' => '',
        'Poštová adresa - Kontaktná osoba' => '',
        'Poštová adresa - Firma' => '',
        'Poštová adresa - Stredisko' => '',
        'Poštová adresa - Prevádzka' => '',
        'Poštová adresa - Ulica' => '',
        'Poštová adresa - PSČ' => '',
        'Poštová adresa - Mesto' => '',
        '' => '',
        'Typ zľavy za doklad' => '',
        'Zľava za doklad' => '',
        'rezervované' => '',
        'Kontaktná osoba' => '',
        'Telefón' => '',
        'Uplatňovanie DPH podľa úhrad' => '',
    );

    $r01 = implode("\t", $row);
    $r01 = iconv('UTF-8', 'WINDOWS-1250//IGNORE', $r01);
    $r_02 = implode("\r\n", $r02);
    $r_02 = iconv('UTF-8', 'WINDOWS-1250', $r_02);

    $txt[] = "$r01\r\n$r_02";
}

$txt = "R00\tT01\r\n".implode("\r\n", $txt);
header('Content-Disposition: attachment; filename=tg-omega-faktury.txt');
echo $txt;

function apiCall($query)
{
    $header = array(
        'Content-type: application/json',
        'Authorization: Bearer '.TG_PRIVILIGED_CODE,
    );

    $ch = curl_init(TG_API_URL.$query);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    $response = curl_exec($ch);
    curl_close($ch);

    return json_decode($response, true);
}
