<?php

namespace NetArz\WhmcsAi;

/**
 * What the agent may know about the one customer it is talking to: name,
 * services, domains, unpaid invoices. Built from localAPI and a fixed list of
 * fields — passwords, usernames, server IPs, custom fields and notes are never
 * copied, so the model cannot be talked into repeating them.
 */
class ClientContext
{
    /** Fields copied from a service. Nothing else leaves WHMCS. */
    private const SERVICE_FIELDS = ['name', 'groupname', 'domain', 'status', 'billingcycle', 'recurringamount', 'nextduedate', 'regdate'];

    public static function forClient(int $clientId): string
    {
        if ($clientId <= 0 || ! Settings::bool('knowledge_client')) {
            return '';
        }

        $details = Whmcs::api('GetClientsDetails', ['clientid' => $clientId, 'stats' => false]);
        if (($details['result'] ?? '') !== 'success') {
            return '';
        }

        $client = isset($details['client']) && is_array($details['client']) ? $details['client'] : $details;
        $currency = (string) ($client['currency_code'] ?? '');

        $lines = [];
        $lines[] = 'Name: '.trim(($client['firstname'] ?? '').' '.($client['lastname'] ?? ''))
            .(! empty($client['companyname']) ? ' ('.$client['companyname'].')' : '');
        $lines[] = 'Client id: '.$clientId.' · account status: '.($client['status'] ?? 'Active');
        if (isset($client['credit']) && (float) $client['credit'] > 0) {
            $lines[] = 'Account credit: '.$client['credit'].' '.$currency;
        }

        $services = self::rows(Whmcs::api('GetClientsProducts', ['clientid' => $clientId, 'limitnum' => 30]), 'products', 'product');
        if ($services) {
            $lines[] = "\nServices:";
            foreach ($services as $service) {
                $safe = array_intersect_key($service, array_flip(self::SERVICE_FIELDS));
                $lines[] = '- '.($safe['groupname'] ?? '').' › '.($safe['name'] ?? '')
                    .(! empty($safe['domain']) ? ' ('.$safe['domain'].')' : '')
                    .' — '.($safe['status'] ?? '')
                    .(! empty($safe['billingcycle']) ? ', '.$safe['billingcycle'] : '')
                    .(isset($safe['recurringamount']) && (float) $safe['recurringamount'] > 0 ? ' '.$safe['recurringamount'].' '.$currency : '')
                    .(! empty($safe['nextduedate']) && $safe['nextduedate'] !== '0000-00-00' ? ', next due '.$safe['nextduedate'] : '');
            }
        }

        $domains = self::rows(Whmcs::api('GetClientsDomains', ['clientid' => $clientId, 'limitnum' => 30]), 'domains', 'domain');
        if ($domains) {
            $lines[] = "\nDomains:";
            foreach ($domains as $domain) {
                $lines[] = '- '.($domain['domainname'] ?? '').' — '.($domain['status'] ?? '')
                    .(! empty($domain['expirydate']) && $domain['expirydate'] !== '0000-00-00' ? ', expires '.$domain['expirydate'] : '')
                    .(isset($domain['donotrenew']) ? ((int) $domain['donotrenew'] === 1 ? ', auto-renew off' : ', auto-renew on') : '');
            }
        }

        $invoices = self::rows(Whmcs::api('GetInvoices', ['userid' => $clientId, 'status' => 'Unpaid', 'limitnum' => 10]), 'invoices', 'invoice');
        $base = Whmcs::systemUrl();
        if ($invoices) {
            $lines[] = "\nUnpaid invoices:";
            foreach ($invoices as $invoice) {
                $id = (int) ($invoice['id'] ?? 0);
                $lines[] = '- #'.$id.' — '.($invoice['total'] ?? '').' '.($invoice['currencycode'] ?? $currency)
                    .', due '.($invoice['duedate'] ?? '').' — pay: '.$base.'viewinvoice.php?id='.$id;
            }
        } else {
            $lines[] = "\nUnpaid invoices: none";
        }

        return Text::limit(implode("\n", $lines), 5000);
    }

    /** localAPI nests lists as ['products' => ['product' => [...]]]; flatten that. */
    private static function rows(array $response, string $outer, string $inner): array
    {
        if (($response['result'] ?? '') !== 'success') {
            return [];
        }
        $list = $response[$outer][$inner] ?? [];

        return is_array($list) ? array_values(array_filter($list, 'is_array')) : [];
    }
}
