<?php
$_GET["sessionid"] = $_GET["sessionid"]=="" ? $_SESSION["pagseguro_id"] : $_GET["sessionid"];
require_once("pagseguro/pgs.php");
require_once("pagseguro/tratadados.php");

$nzshpcrt_gateways[$num] = array(
	'name' => 'PagSeguro',
	'api_version' => 2.0,
	'class_name' => 'wpsc_merchant_pagseguro',
	'has_recurring_billing' => true,
	'display_name' => 'PagSeguro',	
	'wp_admin_cannot_cancel' => false,
	'form' => 'form_pagseguro',
	'internalname' => 'wpsc_merchant_pagseguro',
);
/**
 * wpsc_merchant_pagseguro
 *
 * Classe para comunicação do módulo com o gateway de pagamento
 *
 */ 
class wpsc_merchant_pagseguro extends wpsc_merchant 
{
    /**
     * _process_gateway_pagseguro
     *
     * Tratamento dos dados antes de enviar para o gateway de pagamento.
     * Exibe o formulário com os dados para envio das informações requeridas
     * @param long $sessionid ID de referência para o pedido
     *
     */
    function _process_gateway_pagseguro($sessionid) 
    {
        global $wpsc_cart;

        // Carregando os dados
        $cart = $wpsc_cart;

        $options = array(
            'email_cobranca' => get_option('pagseguro_email'),
            'ref_transacao'  => $sessionid,
            'encoding'       => 'utf-8',
            'item_frete_1'   => number_format(($cart->total_tax + $cart->base_shipping) * 100, 0, '', ''),
        );

        // Dados do cliente
        $_client = $this->cart_data['billing_address'];

        $street = explode(',', $_client['address']);         
        $street = array_slice(array_merge($street, array("", "", "", "")), 0, 4); 
        list($rua, $numero, $complemento, $bairro) = $street;    

        $client = array (
            'nome'   => $_client['first_name'] . " " . $_client['last_name'],
            'cep'    => preg_replace("/[^0-9]/","", $_client['post_code']),
            'end'    => $rua,
            'num'    => $numero,
            'compl'  => $complemento,
            'bairro' => $bairro,
            'cidade' => $_client['city'],
            'uf'     => $_client['state'],
            'pais'   => $_client['country'],
            'email'  => $this->cart_data['email_address']
        );
        
        $products = array();
        foreach($cart->cart_items as $item) {
            $products[] = array(
                "id"         => (string) $item->product_id,
                "descricao"  => $item->product_name,
                "quantidade" => $item->quantity,
                "valor"      => $item->unit_price,
                "peso"       => intval(round($item->weight * 453.59237))
            );
        }

        $PGS = New pgs($options);
        $PGS->cliente($client);	
        $PGS->adicionar($products);
        $show = array(
            "btn_submit"  => 0,
            "print"       => false, 
            "open_form"   => false,
            "show_submit" => false
        );

        $form = $PGS->mostra($show);

        $_SESSION["pagseguro_id"] = $sessionid;
        echo '<form id="form_pagseguro" action="https://pagseguro.uol.com.br/checkout/checkout.jhtml" method="post">',
            $form,
            '<script>window.onload=function(){form_pagseguro.submit();}</script>';
        exit();
    }
    
    /*
     * submit()
     *
     * Chamado após a confirmação do pedido na pág. de checkout
     * @return bool true
     *
     */
    function submit() 
    {
        $this->_process_gateway_pagseguro($this->cart_data['session_id']);
        //$this->go_to_transaction_results($this->cart_data['session_id']);
        return true;
    }
}

/**
 * form_pagseguro
 *
 * Exibe o formulário de configuração do método de pagamento, dados do pagseguro
 * @return string Html do formulário
 *
 */
function form_pagseguro() 
{
    $output = "<tr>\n\r";
    $output .= "<tr>\n\r";
    $output .= "	<td colspan='2'>\n\r";
    $output .= "<strong>".TXT_WPSC_PAYMENT_INSTRUCTIONS_DESCR.":</strong><br />\n\r";
    $output .= "Email vendedor <input type=\"text\" name=\"pagseguro_email\" value=\"" . get_option('pagseguro_email') . "\"/><br/>\n\r";
    $output .= "TOKEN <input type=\"text\" name=\"pagseguro_token\" value=\"" . get_option('pagseguro_token') . "\"/><br/>\n\r";
    $output .= "<em>".TXT_WPSC_PAYMENT_INSTRUCTIONS_BELOW_DESCR."</em>\n\r";
    $output .= "	</td>\n\r";
    $output .= "</tr>\n\r";
    return $output;
}

/**
 * transact_url()
 *
 * Verifica o post do pagseguro e atualiza o pedido com o status da transação
 *
 */
function transact_url()
{
    if(!function_exists("retorno_automatico")) {
        define ('TOKEN', get_option("pagseguro_token"));
        function retorno_automatico ($post)
        {
            global $wpdb;
            switch(strtolower($post->StatusTransacao)) {
            case "completo":case "aprovado":
                $sql = "UPDATE `".WPSC_TABLE_PURCHASE_LOGS."` SET `processed`= '2' WHERE `sessionid`=".$post->Referencia;
                $wpdb->query($sql);
            case "cancelado":
                break;
            }
        }
        require_once("pagseguro/retorno.php");
    }
}

/**
 * pgs_return()
 *
 * Sensível ao carregamento da pág. de retorno (transaction_results), executa o 
 * transact_url caso tenha recebido um post
 *
 */
function pgs_return() {
    if ($_SERVER['REQUEST_METHOD']=='POST' and $_POST) {
        if( get_option('transact_url')=="http://".$_SERVER["SERVER_NAME"].$_SERVER["REQUEST_URI"]){ transact_url();}
    }
}
add_action('init', 'pgs_return');

