<?php
/**
 * pagseguro 
 * 
 * @package 
 * @version 1.0
 * @author DGmike <http://dgmike.com.br> 
 */
class pagseguro
{
    public $internal_name = 'pagseguro';
    public $name          = 'PagSeguro';
    public $is_external   = true;
    public $needs_zipcode = true;

    public function getName()
    {
        return $this->name;
    }

    public function getInternalName()
    {
        return $this->internal_name;
    }

    public function getForm () 
    {
        $shipping = get_option('pagseguro_shipping_configs');
        if (!is_array($shipping)) {
            $shipping = array();
        }
        extract($shipping+array(
            'cep' => '',
            'fixo_no_sedex' => '',
            'fixo_no_pac' => '',
            'fixo_sedex_up_kg' => '',
            'fixo_pac_up_kg' => '',
            'fixo_sedex_up_valor' => '',
            'fixo_pac_up_valor' => '',
            'meio' => array(
                    'Sedex' => '0',
                    'PAC' => '0'
                )
            )
        );
        $checked_sedex = $meio['Sedex'] == '1' ? ' checked="checked" ' : '';
        $checked_pac = $meio['PAC'] == '1' ? ' checked="checked" ' : '';
        return <<<EOF
<tr><td>
<p>
    <label>
        <span>Informe seu CEP (XXXXX-XXX): </span><br />
        <input type="text" name="shipping[cep]" value="$cep" />
    </label><br />
    <label>
        <span>Valor fixo caso não seja possível calcular o frete via SEDEX (por item):</span><br />
        <input type="text" name="shipping[fixo_no_sedex]" value="$fixo_no_sedex" />
    </label><br />
    <label>
        <span>Valor fixo caso não seja possível calcular o frete via encomenda normal (PAC) (por item):</span><br />
        <input type="text" name="shipping[fixo_no_pac]" value="$fixo_no_pac" />
    </label><br />
    <label>
        <span>Valor fixo para SEDEX caso o peso total dos produtos seja superior a 30kg (por item):</span><br />
        <input type="text" name="shipping[fixo_sedex_up_kg]" value="$fixo_sedex_up_kg" />
    </label><br />
    <label>
        <span>Valor fixo para PAC caso o peso total dos produtos seja superior a 30kg (por item):</span><br />
        <input type="text" name="shipping[fixo_pac_up_kg]" value="$fixo_pac_up_kg" />
    </label><br />

    <label>
        <span>Valor fixo para SEDEX caso o valor total dos produtos seja superior a R$ 10.000,00 (por item):</span><br />
        <input type="text" name="shipping[fixo_sedex_up_valor]" value="$fixo_sedex_up_valor" />
    </label><br />
    <label>
        <span>Valor fixo para PAC caso o valor total dos produtos seja superior a R$ 10.000,00 (por item):</span><br />
        <input type="text" name="shipping[fixo_pac_up_valor]" value="$fixo_pac_up_valor" />
    </label><br />
    <input type="hidden" name="shipping[meio][Sedex]" value="0" />
    <input type="hidden" name="shipping[meio][PAC]" value="0" />
    <label> Mostrar estes meios de envio (escolha pelo menos um): </label><br />
    <label><input type="checkbox" name="shipping[meio][Sedex]" value="1" $checked_sedex /> Sedex</label><br />
    <label><input type="checkbox" name="shipping[meio][PAC]" value="1" $checked_pac /> PAC</label>
</p>

<h4>Como configurar?</h4>

<p>Entre no site do <a href="https://pagseguro.uol.com.br" target="_blank">PagSeguro</a> e entre com seu usuário e senha.</>

<p>Entre no menu <strong>Meus Dados</strong> e acesse, em <strong>Configuração de Checkout</strong>, a opção <strong>Preferências Web e frete</strong>.</p>

<p>Na <strong>Definição de Cálculo do frete</strong> deixe a opção <strong>Fete fixo com desconto</strong> marcada, e configure o <strong>Valor do frete para itens extra</strong> definido como <strong>0,00</strong> conforme a figura.</p>

<div style="border:1px solid #CCC;padding:10px;background:#FDFDFD;">
    <a href="../wp-content/plugins/wp-e-commerce/shipping/pagseguro-frete.png" title="Clique e veja ampliado" target="_blank">
        <img src="../wp-content/plugins/wp-e-commerce/shipping/pagseguro-frete.png" width="100%" />
    </a>
    <p><em>Tela que você encontrará no PagSeguro</em></p>
</div>
</td></tr>

EOF;
    }

    public function submit_form() 
    {
        if(isset($_POST['shipping'])) {
            $shipping  = (array)get_option('pagseguro_shipping_configs');
            $submitted = (array)$_POST['shipping'];
            $values = array_merge($shipping, $submitted);
            $values = array_intersect_key($values, array(
                    'cep' => true,
                    'fixo_no_sedex' => true,
                    'fixo_no_pac' => true,
                    'fixo_sedex_up_kg' => true,
                    'fixo_pac_up_kg' => true,
                    'fixo_sedex_up_valor' => true,
                    'fixo_pac_up_valor' => true,
                    'meio' => array(
                            'Sedex' => '0',
                            'PAC' => '0')
                        )
                    );
            update_option('pagseguro_shipping_configs', $values);
        }
        return true;
	}

    public function getQuote( $for_display = false )
    {
        require_once(dirname(__FILE__).'/pagseguro/frete.php');
        global $wpdb, $wpsc_cart;
        $zipcode = '';
        if(isset($_POST['zipcode'])) {
            $zipcode = $_POST['zipcode'];      
            $_SESSION['wpsc_zipcode'] = $_POST['zipcode'];
        } else if(isset($_SESSION['wpsc_zipcode'])) {
            $zipcode = $_SESSION['wpsc_zipcode'];
        }
        if (!$zipcode) { // Este meio de fretamento só funcionará se tiver ZipCode
            // return null;
            $zipcode = '00000-000';
        }
        $shipping = get_option('pagseguro_shipping_configs');
        if (!is_array($shipping)) {
            $shipping = array();
        }
        extract($shipping+array('cep' => '','valor_fixo' => '', 'meio' => array('Sedex'=>'0', 'PAC'=> '0')));
        // Calculando o valor e o peso total
        $peso = 0;
        $preco = 0;

        foreach ((array)$wpsc_cart->cart_items as $item) {
            $preco += $item->total_price;
            $peso += $this->converteValor($item->weight, 'gram')*$item->quantity;
        }

        $frete = new PgsFrete();
        $peso = number_format($peso/1000, 2, '.', '');
        $preco = number_format($preco, 2, ',', '');
        $zipcode = preg_replace('@\D@', '', $zipcode);
        //$zipcode = substr($zipcode, 0, 5).'-'.substr($zipcode, 5);

        $oFrete = $frete->gerar($cep, $peso, $preco, $zipcode);

        if ($meio['Sedex'] == '0') {
            unset($oFrete['Sedex']);
        }
        if ($meio['PAC'] == '0') {
            unset($oFrete['PAC']);
        }

        return $oFrete;
    }

    public function converteValor($weight, $unit)
    {
        switch($unit) {
            case "kilogram":
            $weight = $weight * 0.45359237;
            break;

            case "gram":
            $weight = $weight * 453.59237;
            break;

            case "once":
            case "ounces":
            $weight = $weight * 16;
            break;

            default:
            $weight = $weight;
            break;
        }
        return $weight;
    }
}

$pagseguro = new pagseguro();
$wpsc_shipping_modules[$pagseguro->getInternalName()] = $pagseguro;
