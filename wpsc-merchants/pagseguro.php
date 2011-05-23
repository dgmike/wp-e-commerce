<?php
$_GET["sessionid"] = $_GET["sessionid"]=="" ? $_SESSION["pagseguro_id"] : $_GET["sessionid"];
require_once("pagseguro/pgs.php");
require_once("pagseguro/tratadados.php");

$nzshpcrt_gateways[$num]['name'] = 'PagSeguro';
$nzshpcrt_gateways[$num]['admin_name'] = 'PagSeguro';
$nzshpcrt_gateways[$num]['internalname'] = 'pagseguro';
$nzshpcrt_gateways[$num]['function'] = 'gateway_pagseguro';
$nzshpcrt_gateways[$num]['form'] = "form_pagseguro";
$nzshpcrt_gateways[$num]['submit_function'] = "submit_pagseguro";


//if( get_option('transact_url')=="http://".$_SERVER["SERVER_NAME"].$_SERVER["REDIRECT_URL"]){ transact_url();}

function gateway_pagseguro($seperator, $sessionid) 
{
    global $wpdb;

    // Carregando os dados
    $cart = unserialize($_SESSION['wpsc_cart']);

    $options = array(
        'email_cobranca' => get_option('pagseguro_email'),
        'ref_transacao'  => $sessionid,
        'encoding'       => 'utf-8',
        'item_frete_1'   => number_format(($cart->total_tax + $cart->base_shipping) * 100, 0, '', ''),
    );
    // Dados do cliente
    $_cliente = $_POST["collected_data"];
    list($ddd,$telefone)   = trataTelefone($_cliente[17]);

    $street=explode(',',$_cliente[4]);            
    $street = array_slice(array_merge($street, array("","","","")),0,4); 
    list($rua, $numero, $complemento, $bairro) = $street;    
    
    $cliente = array (
        'nome'   => $_POST["collected_data"][2] . " " . $_cliente[3],
        'cep'    => preg_replace("/[^0-9]/","", $_cliente[7]),
        'end'    => $rua,
        'num'    => $numero,
        'compl'  => $complemento,
        'bairro' => $bairro,
        'cidade' => $_cliente[5],
        'uf'     => $_cliente[14],
        'pais'   => $_cliente[6][0],
        'ddd'    => $ddd,
        'tel'    => $telefone,
        'email'  => $_cliente[8]
    );
    // Usando a session, isso é correto
    $cart = $cart->cart_items;
    
    $produtos = array();
    foreach($cart as $item) {
        $produtos[] = array(
            "id"         => (string) $item->product_id,
            "descricao"  => $item->product_name,
            "quantidade" => $item->quantity,
            "valor"      => $item->unit_price,
            "peso"       => intval(round($item->weight * 453.59237))
        );
    }

    $PGS = New pgs($options);
    $PGS->cliente($cliente);	
    $PGS->adicionar($produtos);
    $mostra = array(
        "btn_submit"  => 0,
        "print"       => false, 
        "open_form"   => false,
        "show_submit" => false
    );

    $form = $PGS->mostra($mostra);

    $_SESSION["pagseguro_id"] = $sessionid;
    echo '<form id="form_pagseguro" action="https://pagseguro.uol.com.br/checkout/checkout.jhtml" method="post">',
        $form,
        '<script>window.onload=function(){form_pagseguro.submit();}</script>';
    exit();
}

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

function submit_pagseguro() 
{
    if($_POST['pagseguro_email'] != null) {
        update_option('pagseguro_email', $_POST['pagseguro_email']);
    }
    if($_POST['pagseguro_token'] != null) {
        update_option('pagseguro_token', $_POST['pagseguro_token']);
    }
    return true;
}

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

##function retorno_automatico($post){
##    global $wpdb;
##    
##}
#class PagSeguro_StandardController extends wpsc_merchant {
#	/**
#	* parse_gateway_notification method, receives data from the payment gateway
#	* @access private
#	*/
#	function retornoPagSeguro($post) {
#	    global $wpdb;

#	    $version = str_replace(".","",WPSC_PRESENTABLE_VERSION);

#		/// PayPal first expects the IPN variables to be returned to it within 30 seconds, so we do this first.
##		$paypal_url = get_option('paypal_multiple_url');
##		$received_values = array();
##		$received_values['cmd'] = '_notify-validate';
##  		$received_values += $_POST;
##		$options = array(
##			'timeout' => 5,
##			'body' => $received_values,
##			'user-agent' => ('WP e-Commerce/'.WPSC_PRESENTABLE_VERSION)
##		);

##		$response = wp_remote_post($paypal_url, $options);
#		if (in_array(strtolower($post->StatusTransacao), array('completo', 'aprovado'))) {

#		
#			$this->paypal_ipn_values = $received_values;
#			$this->session_id = $post->Referencia;
#			
#			$this->set_purchase_processed_by_sessionid(3);

#		} else {
#			//exit("IPN Request Failure");
#		}
#	}
#}

function pgs_return() {
    if ($_SERVER['REQUEST_METHOD']=='POST' and $_POST) {
        if( get_option('transact_url')=="http://".$_SERVER["SERVER_NAME"].$_SERVER["REQUEST_URI"]){ transact_url();}
    }
}
add_action('init', 'pgs_return');

