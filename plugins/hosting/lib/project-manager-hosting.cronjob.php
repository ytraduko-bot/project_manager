<?php

class rex_cronjob_project_manager_hosting extends rex_cronjob
{

    public function execute()
    {

        $websites = rex_sql::factory()->setDebug(0)->getArray('SELECT D.domain AS domain FROM
        (SELECT domain, createdate FROM rex_project_manager_domain_hosting) AS H
        RIGHT JOIN
        (SELECT domain, updatedate, is_ssl FROM rex_project_manager_domain) AS D
        ON
        H.domain = D.domain
        GROUP BY D.domain
        ORDER BY H.createdate ASC');
        
        
        $message = '';
        $error = false;
   
        foreach($websites as $website) {
          
          // because call limit ip-api.com
          usleep(500000);          
          $domain = $website['domain'];
          $ip = gethostbyname(idn_to_ascii($domain));   
          $url = 'http://ip-api.com/json/'.$ip;
          
          $ch = curl_init();
          
          $options = array(
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_AUTOREFERER    => true,
            CURLOPT_MAXREDIRS    => 4,
            CURLOPT_HEADER         => false,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 1000,
            CURLOPT_URL => $url
          );
          
          curl_setopt_array($ch, $options);
          $resps[$domain .";hosting"] = curl_exec($ch);
          
          curl_close($ch);
          
        }
          
         foreach ($resps as $key => $response) {

            $domain = explode(";", $key)[0];
            $mode = explode(";", $key)[1];
                
            $hosting = json_decode($response, true);
            
            $ip = '';
            $ip = gethostbyname(idn_to_ascii($domain));   

            if(json_last_error() === JSON_ERROR_NONE && (!array_key_exists("message", $hosting)))  {
                if($mode == "hosting") {
                  
                    rex_sql::factory()->setDebug(0)->setQuery('INSERT INTO ' . rex::getTable('project_manager_domain_hosting') . ' (`domain`, `raw`, `createdate`, `ip`, `status`) VALUES(:domain, :response, NOW(), :ip, 1) 
                    ON DUPLICATE KEY UPDATE domain = :domain, `raw` = :response, createdate = NOW(), `ip` = :ip, `status` = 1', [":domain" => $domain, ":response" => $response, ":ip" => $ip] );
                    
                } 
            } else {
              
              // ERROR HANDLE
              if($mode == "hosting") {
                
                rex_sql::factory()->setDebug(0)->setQuery('INSERT INTO ' . rex::getTable('project_manager_domain_hosting') . ' (`domain`, `raw`, `createdate`, `ip`, `status`) VALUES(:domain, :response, NOW(), :ip, -1)
                    ON DUPLICATE KEY UPDATE domain = :domain, `raw` = :response, createdate = NOW(), `ip` = :ip, `status` = -1', [":domain" => $domain, ":response" => $response,  ":ip" => $ip] );
                
              } 
              
              $message .= $domain.': '.$hosting['status'].' '.$hosting['message'].'\n';              
              $error = true;

            }
          }
          
        
 
        
        if ($error === true) {
          $this->setMessage($message);
          return false;
        } else {
          return true;
        }

       return true;
    }
    
    public function getTypeName()
    {
        return rex_i18n::msg('project_manager_cronjob_hosting_name');
    }

    public function getParamFields()
    {
        return [];
    }
}
?>