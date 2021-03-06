<?php
/**
 * Gera CSV com base de produtos
 * @author	Christopher Silva <christopher.silva@e-smart.com.br> / Carlos Shirasawa <carlos.shirasawa@e-smart.com.br>
 * @since	2012-09-26
 */

//buffering
ob_start();

$inicio_exec = microtime();
error_reporting(E_ALL);
ini_set('display_errors',1); 
 
#chama classe
require_once("../libs/functions.php");

// Carregando os dados de conexão no arquivo xml do Magento
$configData = simplexml_load_file('../../app/etc/local.xml');
$connectData = $configData->global->resources->default_setup->connection;


// Setando as variáveis de conexão com o banco
$host = (string) $connectData->host;
$user = (string) $connectData->username;
$pass = (string) $connectData->password;
$base = (string) $connectData->dbname;

// Conexão com o banco de dados
$connect = mysql_connect($host, $user, $pass, $base) OR die('');
mysql_select_db($base, $connect);

//pegando os paths
$query = "SELECT path, value FROM core_config_data WHERE path IN ('web/unsecure/base_url', 'web/unsecure/base_media_url') ORDER BY path DESC;";
$paths = getPaths($query);

// Busca dos produtos seguindo os seguintes critérios: configuravel e com simples atribuído
$query = "SELECT DISTINCT conf.entity_id ";
$query.= "FROM ";
$query.= "catalog_product_entity AS conf INNER JOIN ";
$query.= "catalog_product_relation AS rel ON (rel.parent_id = conf.entity_id) ";
$query.= "WHERE ";
$query.= "  conf.type_id = 'configurable';";

$products = exec_query($query);


// Inicia interações nos produtos selecionados para popular o objeto XML;
$productData = array();
$count = 0;
$total_count = 0;

foreach ($products AS $product) {
	$product_id = $product['entity_id'];
	$count++;
	
	// Recupera as informações do produto configurável
	$query = "SELECT ";
	$query.= "  ent.sku, ";
	$query.= "  url.value AS url, ";
	$query.= "  name.value AS nome, ";
	$query.= "  dsc.value AS descricao, ";
	$query.= "  'Hering' AS marca, ";
	$query.= "  price.value AS preco_de, ";
	/*$query.= "  CASE WHEN ( ";
	$query.= "      (sfr.value < NOW() AND (sto.value > NOW() OR sto.value IS NULL)) OR ";
	$query.= "      (sfr.value IS NULL AND sto.value > NOW()) OR ";
	$query.= "      (sfr.value IS NULL AND sto.value IS NULL) ";
	$query.= "    ) THEN price.value ";
	$query.= "    ELSE NULL ";
	$query.= "  END AS preco_por, ";*/
	$query.= "  priceto.value AS preco_por, ";
	$query.= "  img.value AS imagem ";
	$query.= "FROM ";
	$query.= "  catalog_product_entity AS ent ";
	$query.= "  LEFT JOIN catalog_product_entity_varchar AS url ON ";
	$query.= "    (";
	$query.= "    url.entity_id = ent.entity_id AND ";
	$query.= "    url.attribute_id = (SELECT attribute_id FROM eav_attribute WHERE entity_type_id = 4 AND attribute_code = 'url_path') AND ";
	$query.= "    url.store_id = 0";
	$query.= "    ) ";
	$query.= "  LEFT JOIN catalog_product_entity_varchar AS name ON  ";
	$query.= "    ( ";
	$query.= "    name.entity_id = ent.entity_id AND  ";
	$query.= "    name.attribute_id = (SELECT attribute_id FROM eav_attribute WHERE entity_type_id = 4 AND attribute_code = 'name') AND  ";
	$query.= "    name.store_id = 0 ";
	$query.= "    ) ";
	$query.= "  LEFT JOIN catalog_product_entity_text AS dsc ON  ";
	$query.= "    ( ";
	$query.= "    dsc.entity_id = ent.entity_id AND  ";
	$query.= "    dsc.attribute_id = (SELECT attribute_id FROM eav_attribute WHERE entity_type_id = 4 AND attribute_code = 'description') AND  ";
	$query.= "    dsc.store_id = 0 ";
	$query.= "    ) ";
	$query.= "  LEFT JOIN catalog_product_entity_varchar AS img ON  ";
	$query.= "    (";
	$query.= "    img.entity_id = ent.entity_id AND ";
	$query.= "    img.attribute_id = (SELECT attribute_id FROM eav_attribute WHERE entity_type_id = 4 AND attribute_code = 'image') AND ";
	$query.= "    img.store_id = 0";
	$query.= "    ) ";
	$query.= "  LEFT JOIN catalog_product_entity_datetime AS sfr ON ";
	$query.= "    ( ";
	$query.= "     sfr.entity_id = ent.entity_id AND ";
	$query.= "     sfr.attribute_id = (SELECT attribute_id FROM eav_attribute WHERE entity_type_id = 4 AND attribute_code = 'special_from_date') AND ";
	$query.= "     sfr.store_id = 0 ";
	$query.= "     ) ";
	$query.= "  LEFT JOIN catalog_product_entity_datetime AS sto ON ";
	$query.= "    ( ";
	$query.= "    sto.entity_id = ent.entity_id AND ";
	$query.= "    sto.attribute_id = (SELECT attribute_id FROM eav_attribute WHERE entity_type_id = 4 AND attribute_code = 'special_to_date') AND ";
	$query.= "    sto.store_id = 0 ";
	$query.= "    ) ";
	$query.= "  LEFT JOIN catalog_product_entity_decimal AS price ON ";
	$query.= "    ( ";
	$query.= "    price.entity_id = ent.entity_id AND ";
	$query.= "    price.attribute_id = (SELECT attribute_id FROM eav_attribute WHERE attribute_code = 'price') ";
	$query.= "    ) ";
	$query.= "  LEFT JOIN catalog_product_entity_decimal AS priceto ON ";
	$query.= "    ( ";
	$query.= "    priceto.entity_id = ent.entity_id AND ";
	$query.= "    priceto.attribute_id = (SELECT attribute_id FROM eav_attribute WHERE attribute_code = 'special_price') ";
	$query.= "    ) ";
	$query.= "WHERE ent.entity_id = $product_id;";

	$entityData = current(exec_query($query));
	
	// Recupera as informações de categorias
	$query = "SELECT cat.value AS categoria ";
	$query.= "FROM catalog_category_product AS rel ";
	$query.= "  INNER JOIN catalog_category_entity AS ct ON (ct.entity_id = rel.category_id) ";
	$query.= "  INNER JOIN catalog_category_entity_varchar AS cat ON (cat.entity_id = ct.entity_id AND attribute_id = (SELECT attribute_id FROM eav_attribute WHERE entity_type_id = 3 AND attribute_code = 'name')) ";
	$query.= "WHERE rel.product_id = $product_id";

	$categories = exec_query($query);
  
	$cats = array();
	foreach ($categories AS $category) {
		$cats[] = $category['categoria'];
	}
	
	$strCategoria = implode(',', $cats);
  
	// Recupera as informações relativas aos produtos simples
	$query = "SELECT cor.value AS color, cor.option_id AS colorid, img.value AS image, tam.value AS size, tam.option_id AS sizeid, simple.sku AS sku, simple.entity_id AS simpleid, ";
	$query.= " CASE stk.qty WHEN stk.qty > 0 THEN 'available' ELSE 'unavailable' END AS stock ";
	$query.= "FROM ";
	$query.= "  catalog_product_entity_media_gallery AS img ";
	$query.= "  INNER JOIN catalog_product_relation AS rel ON (rel.parent_id = img.entity_id) ";
	//$query.= "  INNER JOIN cataloginventory_stock_item AS stk ON (stk.product_id = rel.child_id and qty > 0) ";
	$query.= "  INNER JOIN cataloginventory_stock_item AS stk ON (stk.product_id = rel.child_id) ";
	$query.= "  INNER JOIN catalog_product_entity_int AS opt ON ";
	$query.= "    ( ";
	$query.= "    opt.entity_id = rel.child_id AND ";	
	$query.= "    opt.attribute_id = (SELECT attribute_id FROM eav_attribute WHERE entity_type_id = 4 AND attribute_code = 'color') AND ";
	$query.= "    opt.store_id = 0 ";
	$query.= "    ) ";
	$query.= "  INNER JOIN eav_attribute_option_value AS val ON (val.option_id = opt.value AND val.store_id = 0) ";
	$query.= "  INNER JOIN eav_attribute_option_value AS cor ON (cor.option_id = opt.value AND cor.store_id = 1) ";
	$query.= "  INNER JOIN catalog_product_entity_int AS cpi ON ";
	$query.= "    ( ";
	$query.= "    cpi.entity_id = rel.child_id AND ";
	$query.= "    cpi.attribute_id = (SELECT attribute_id FROM eav_attribute WHERE entity_type_id = 4 AND attribute_code = 'size') AND ";
	$query.= "    cpi.store_id = 0 ";
	$query.= "    ) ";
	$query.= "  INNER JOIN eav_attribute_option_value AS tam ON (tam.option_id = cpi.value AND tam.store_id = 0) ";
	$query.= "  INNER JOIN catalog_product_entity AS simple ON ";
	$query.= "    ( ";
	$query.= "    simple.entity_id = rel.child_id ";
	$query.= "    ) ";
	$query.= "WHERE ";
	$query.= "  img.entity_id = $product_id "; 
	$query.= "  AND img.value LIKE CONCAT('%',TRIM(val.value),'%');";
	
	/*$query = "SELECT simple.sku AS sku ";
	$query.= "FROM ";
	$query.= " catalog_product_entity AS simple ";
	$query.= " INNER JOIN catalog_product_relation AS rel ON (rel.child_id = simple.entity_id)";*/
	//$query = "";
	
	$multiple = exec_query($query);

	$colors = $images = $sizes = Array();
	$count2 = 0;
	foreach ($multiple AS $each) {
		
        $parcelas_special_price = getInstallmentCount($entityData['preco_por']);
        $parcelas_normal_price = getInstallmentCount($entityData['preco_de']);        
            
		$count2++;
		$total_count++;

		//montando o array dos produtos
		//$productData[$count]['id_produto'] = ($entityData['sku']) ? $entityData['sku'] : null;
		$productData[$count][$count2]['id'] = trim(substr($each['sku'],0,-1));
		$productData[$count][$count2]['productInternalParentId'] = $product_id;
		$productData[$count][$count2]['link'] = trim($entityData['url']);
		//$productData[$count][$count2]['imagem'] = $paths['base_media_url'] . 'catalog/product' . trim($each['image']);
		$productData[$count][$count2]['imagem'] = str_replace('/','',trim($each['image']));
		$productData[$count][$count2]['categoria'] = (trim($strCategoria)) ? trim($strCategoria) : null;
		$productData[$count][$count2]['nome'] = trim($entityData['nome']);
		$productData[$count][$count2]['descricao'] = (trim(quotemeta($entityData['descricao']))) ? trim($entityData['descricao']) : null;
		$productData[$count][$count2]['status'] = (trim($each['stock'])) ? trim($each['stock']) : null;
		$productData[$count][$count2]['preco_de'] = ($entityData['preco_de']) ? number_format($entityData['preco_de'], 2, '.', '') : null;
		
		if( trim($entityData['preco_por']) != trim($entityData['preco_de']) && ((int) $entityData['preco_por'] > 0) ) {
			$productData[$count][$count2]['preco_por'] = number_format($entityData['preco_por'], 2, '.', '');
		} else {
			$productData[$count][$count2]['preco_por'] = null;
		}
         
         
        if( $parcelas_special_price['parcelamento_valor'] != 0 ) {
            if( $parcelas_special_price['parcelamento_qntd'] > 1 ) {
                $productData[$count][$count2]['installmentCount'] = $parcelas_special_price['parcelamento_qntd'];
                $productData[$count][$count2]['installmentPrice'] = $parcelas_special_price['parcelamento_valor'];
            } else {
                $productData[$count][$count2]['installmentCount'] = null;
                $productData[$count][$count2]['installmentPrice'] = null;
            }
        } else {
            if( $parcelas_normal_price['parcelamento_qntd'] > 1 ) {
                $productData[$count][$count2]['installmentCount'] = $parcelas_normal_price['parcelamento_qntd'];
                $productData[$count][$count2]['installmentPrice'] = $parcelas_normal_price['parcelamento_valor'];
            } else {
                $productData[$count][$count2]['installmentCount'] = null;
                $productData[$count][$count2]['installmentPrice'] = null;
            }
        }
		
		$productData[$count][$count2]['cor'] = (trim($each['color'])) ? trim($each['color']).':'.trim($each['colorid']) : null;
		$productData[$count][$count2]['tamanho'] = (trim($each['size'])) ? trim($each['size']).':'.trim($each['sizeid']) : null;
	}//end foreach simple product
}//end first foreach

//configs
$delimiter	= ';';
$filename	= 'produtos';
$path		= '../temp';

#gera cabeçalho
$cabecalho	= array("id", "productinternalparentid", "link", "imagem", "categoria", "nome", "descricao", "status", "preco_de", "preco_por", "instalmentcount", "installmentprice", "cor", "tamanho");
#abrindo arquivo, caso nao exista tenta criar
$file = fopen($path . DIRECTORY_SEPARATOR . $filename . '.csv', "w");
#colocando cabecalho no arquivo
fputcsv($file, $cabecalho, $delimiter);

foreach($productData as $configuravel):
	foreach($configuravel as $simples):
		fputcsv($file, $simples, $delimiter);
	endforeach;
endforeach;

fclose($file);

print 'Script executado com sucesso em: ' . number_format((microtime() - $inicio_exec),3) . "<br/>";
print 'Produtos configuraveis: ' . $count . "<br/>";
print 'Produtos simples: ' . $total_count . "<br/>";

if( $path.DIRECTORY_SEPARATOR.$filename.'.csv' ) {
	echo "Arquivo gerado com sucesso!! ";
}



