<?php
namespace LCloss\Namecheap;

class NamecheapApi
{
    const SANDBOX_URL = 'api.sandbox.namecheap.com';
    const PRODUCTION_URL = 'api.namecheap.com';

    const CMD_CREATE = 'namecheap.domains.create';
    const CMD_SETDNS = 'namecheap.domains.dns.setHosts';

    private static $dns_records = [
        ['record'   => '@',         'host' => '%domain%.',      'recordtype' => 'NS',       'value' => 'ns1.%domain%.'],
        ['record'   => '@',         'host' => '%domain%.',      'recordtype' => 'NS',       'value' => 'ns2.%domain%.'],
        ['record'   => '@',         'host' => '%domain%.',      'recordtype' => 'A',        'value' => '%ipv4%'],
        ['record'   => '@',         'host' => '%domain%.',      'recordtype' => 'AAAA',     'value' => '%ipv6%'],
        ['record'   => '@',         'host' => '%domain%.',      'recordtype' => 'MX',       'value' => 'mail.%domain%.'],
        ['record'   => '@',         'host' => '%domain%.',      'recordtype' => 'TXT',      'value' => 'v=spf1 +a +mx +a:%hostname% -all'],
        ['record'   => '_domainconnect.', 'host' => '%domain%.',  'recordtype' => 'TXT',      'value' => 'domainconnect.plesk.com/host/%hostname%/port/8443'],
        ['record'   => '_acme-challenge.', 'host' => '%domain%.',  'recordtype' => 'TXT',      'value' => '%acme_challenge%'],
        ['record'   => '_dmarc.',   'host' => '%domain%.',      'recordtype' => 'TXT',      'value' => 'v=DMARC1; p=none'],
        ['record'   => 'ftp.',      'host' => '%domain%.',      'recordtype' => 'CNAME',   'value' => '%domain%'],
        ['record'   => 'ipv4.',     'host' => '%domain%.',      'recordtype' => 'A',        'value' => '%ipv4%'],
        ['record'   => 'ipv6.',     'host' => '%domain%.',      'recordtype' => 'AAAA',     'value' => '%ipv6%'],
        ['record'   => 'mail.',     'host' => '%domain%.',      'recordtype' => 'A',        'value' => '%ipv4%'],
        ['record'   => 'mail.',     'host' => '%domain%.',      'recordtype' => 'AAAA',     'value' => '%ipv6%'],
        ['record'   => 'ns1.',      'host' => '%domain%.',      'recordtype' => 'A',        'value' => '%ipv4%'],
        ['record'   => 'ns1.',      'host' => '%domain%.',      'recordtype' => 'AAAA',     'value' => '%ipv6%'],
        ['record'   => 'ns2.',      'host' => '%domain%.',      'recordtype' => 'A',        'value' => '%ipv4%'],
        ['record'   => 'ns2.',      'host' => '%domain%.',      'recordtype' => 'AAAA',     'value' => '%ipv6%'],
        ['record'   => 'webmail.',  'host' => '%domain%.',      'recordtype' => 'A',        'value' => '%ipv4%'],
        ['record'   => 'webmail.',  'host' => '%domain%.',      'recordtype' => 'AAAA',     'value' => '%ipv6%'],
    ];

    protected $key;
    protected $user;
    protected $service;

    public function __construct( $user, $key, $ip, $service = 'production' )
    {
        $this->setUser( $user );
        $this->setKey( $key );
        $this->setIp( $ip );
        $this->setService( $service );
    }
    public function setIp( $ip )
    {
        $this->ip = $ip;
    }
    public function setKey( $key ) 
    {
        $this->key = $key;
    }
    public function setService( $service )
    {
        $this->service = $service;
    }
    public function setUser( $user )
    {
        $this->user = $user;
    }

    public function createDomain( $domain, $contacts, $years = 1, $free_whois = 1, $wgenabled = 1, $admin_order = 0, $premium = 0 )
    {
        $api_call = self::CMD_CREATE;
        $api_call .= '&DomainName=' . $domain;
        $api_call .= '&Years=' . $years;

        foreach( $contacts as $contact_type => $contact ) {
            foreach( $contact as $field => $value ) {
                $api_call .= '&' . $contact . $field . '=' . urlencode($value);
            }
        }

        $api_call .= '&AddFreeWhoisguard=' . ($freewhois == 1 ? 'yes' : 'no');
        $api_call .= '&WGEnabled=' . ($wgenabled == 1 ? 'yes' : 'no');
        $api_call .= '&GenerateAdminOrderRefId=' . ($admin_order == 1 ? 'yes' : 'no');
        $api_call .= '&IsPremiumDomain=' . ($premium == 1 ? 'True' : 'False');

        return $this->call( $api_call );
    }

    public function setDns( $domain, $ip, $ipv6 = '', $setNS = 0, $setDMARC = 1, $hostname = '', $acme_challenge = '', $only_acme = 0 )
    {
        $api_call = self::CMD_SETDNS;

        $domain_pattern = '/([^.]*)?\.(.*)$/';
        preg_match( $domain_pattern, $domain, $matches );

        $api_call .= "&SLD=" . $matches[1] . "&TLD=" . $matches[2];

        $records = [];
        foreach( self::$dns_records as $dns_record ) {
            $dns_record['host'] = str_replace( '%domain%', $domain, $dns_record['host']);
            $dns_record['value'] = str_replace( '%domain%', $domain, $dns_record['value']);
            $dns_record['value'] = str_replace( '%ipv4%', $ip, $dns_record['value']);
            $dns_record['value'] = str_replace( '%ipv6%', $ipv6, $dns_record['value']);
            $dns_record['value'] = str_replace( '%hostname%', $hostname, $dns_record['value']);
            $dns_record['value'] = str_replace( '%acme_challenge%', $acme_challenge, $dns_record['value']);

            $count = 0;
            if( 'AAAA' == $dns_record['recordtype'] ) {
                if ( 0 == $only_acme && !empty($ipv6) ) {
                    $count++;

                    $api_call .= "&HostName" . $count . "=" . $dns_record['record']; 
                    $api_call .= "&RecordType" . $count . "=" . $dns_record['recordtype'];
                    $api_call .= "&Address" . $count . "=" . urlencode( $dns_record['value'] );
                }
            } elseif( 'NS' == $dns_record['recordtype'] || 'ns1' == $dns_record['host'] || 'ns2' == $dns_record['host'] ) {
                if( 0 == $only_acme && 1 == $setNS ) {
                    $count++;

                    $api_call .= "&HostName" . $count . "=" . $dns_record['record']; 
                    $api_call .= "&RecordType" . $count . "=" . $dns_record['recordtype'];
                    $api_call .= "&Address" . $count . "=" . urlencode( $dns_record['value'] );
                }
            } elseif( '_dmarc.' == $dns_record['recordtype'] ) {
                if( 0 == $only_acme && 1 == $setDMARC ) {
                    $count++;

                    $api_call .= "&HostName" . $count . "=" . $dns_record['record']; 
                    $api_call .= "&RecordType" . $count . "=" . $dns_record['recordtype'];
                    $api_call .= "&Address" . $count . "=" . urlencode( $dns_record['value'] );
                }
            } elseif( '_acme-challenge.' == $dns_record['record'] ) {
                if( !empty($acme_challenge) ) {
                    $count++;

                    $api_call .= "&HostName" . $count . "=" . $dns_record['record']; 
                    $api_call .= "&RecordType" . $count . "=" . $dns_record['recordtype'];
                    $api_call .= "&Address" . $count . "=" . urlencode( $dns_record['value'] );
                }
            } elseif( ('@' == $dns_record['record'] || '_domainconnect.' == $dns_record['record']) && 
                        'TXT' == $dns_record['recordtype'] && 
                        (substr($dns_record['value'], 0, 6) == 'v=spf1' || substr($dns_record['value'], 0, 13) == 'domainconnect') ) {
                if( 0 == $only_acme && !empty($hostname) ) {
                    $count++;

                    $api_call .= "&HostName" . $count . "=" . $dns_record['record']; 
                    $api_call .= "&RecordType" . $count . "=" . $dns_record['recordtype'];
                    $api_call .= "&Address" . $count . "=" . urlencode( $dns_record['value'] );
                }
            } else {
                if ( 0 == $only_acme ) {
                    $count++;

                    $api_call .= "&HostName" . $count . "=" . $dns_record['record']; 
                    $api_call .= "&RecordType" . $count . "=" . $dns_record['recordtype'];
                    $api_call .= "&Address" . $count . "=" . urlencode( $dns_record['value'] );
    
                    if ( $dns_record['recordtype'] == 'MX' ) {
                        $api_call .= "&MXPref=10&EmailType=MX";
                    }
                }
            }
        }

        return $this->call( $api_call );
    }

    public function call( $command )
    {
        $namecheap_api_call = 'https://%service%/xml.response?ApiUser=%user%&ApiKey=%key%&UserName=%user%&ClientIp=%client_ip%&Command=%command%';

        if( $this->service == 'production' ) {
            $service = self::PRODUCTION_URL;
        } else {
            $service = self::SANDBOX_URL;
        }

        $namecheap_api_call = str_replace('%service%', $service, $namecheap_api_call);
        $namecheap_api_call = str_replace('%user%', $this->user, $namecheap_api_call);
        $namecheap_api_call = str_replace('%key%', $this->key, $namecheap_api_call);
        $namecheap_api_call = str_replace('%client_ip%', $this->ip, $namecheap_api_call);
        $namecheap_api_call = str_replace('%command%', $command, $namecheap_api_call);

        $token = '';
        $headers = array(
            "Authorization: Bearer " . $token,
            "Content-Type: application/json",
            "cache-control: no-cache"
        );

        $curl = curl_init();
        $method = 'GET';

        curl_setopt_array($curl, array(
          CURLOPT_URL => $namecheap_api_call,
          CURLOPT_RETURNTRANSFER => true,
          CURLOPT_ENCODING => "",
          CURLOPT_MAXREDIRS => 10,
          CURLOPT_TIMEOUT => 30,
          CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
          CURLOPT_CUSTOMREQUEST => $method,
        //   CURLOPT_POSTFIELDS => $param_json,
        //   CURLOPT_HTTPHEADER => $headers,
        ));
        
        $response = curl_exec($curl);
        $err = curl_error($curl);
        
        curl_close($curl);
        
        if ($err) {
            $resp = array(
                'status'    => 'error',
                'data'      => $err
            );
        } else {
            $json = json_decode( $response );
            if ( !is_null($json) ) {
                if ( $json->success ) {
                    $resp = array(
                        'status'    => 'success',
                        'data'      => $response
                    );
                } else {
                    $resp = array(
                        'status'    => 'error',
                        'data'      => $response
                    );
                }
            } else {
                $resp = array(
                    'status'    => 'error',
                    'data'      => $response
                );
            }

        }

        return $resp;
    }
}