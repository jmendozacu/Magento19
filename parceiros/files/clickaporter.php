<?php
/*
 *Gerador de XML específico para o click a porter
 */

// Log do tipo catch all que guarda todo erro printado na tela
ob_start();

$inicio_exec = microtime();
error_reporting(E_ALL);
ini_set('display_errors',1);

// Carregando os dados de conexão no arquivo xml do Magento
//$configData = simplexml_load_file('../../app/etc/local.xml');
//$connectData = $configData->global->resources->default_setup->connection;
$connectData = simplexml_load_file('../conf/local.xml');

// Setando as variáveis de conexão com o banco
$host = (string) $connectData->host;
$user = (string) $connectData->username;
$pass = (string) $connectData->password;
$base = (string) $connectData->dbname;

// Conexão com o banco de dados
$connect = mysql_connect($host, $user, $pass, $base) OR die();
mysql_select_db($base, $connect);

// Inicia o objeto de XML
$xml = new SimpleXMLElement("<?xml version=\"1.0\" encoding=\"UTF-8\"?><HERINGWEBSTORE></HERINGWEBSTORE>");
$pData = $xml->addChild('produtos');

// Função simples para retorno de queries em formato de array
function exec_query($query) {
  global $connect;
  $result = array();
  $resource = mysql_query($query, $connect);
  while ($row = mysql_fetch_assoc($resource)) {
    $result[] = $row;
  }
  return $result;
}

function addCData($obj, $cdata_text) {
  $node= dom_import_simplexml($obj); 
  $no = $node->ownerDocument; 
  $node->appendChild($no->createCDATASection(utf8_encode($cdata_text)));
} 

// Recupera as informações de urls
$query = "SELECT path, value FROM core_config_data WHERE path IN ('web/unsecure/base_url', 'web/unsecure/base_media_url') ORDER BY path DESC;";

$arrCore = exec_query($query);
foreach ($arrCore AS $val) {
  $path = preg_replace("/web\/unsecure\//", "", $val['path']);
  if ($val['value'] == '{{base_url}}') {
    $value = "http://heringwebstore.lojaemteste.com.br/";
  } else if (preg_match("/{{unsecure_base_url}}/", $val['value'])) {
    $value = preg_replace("/{{unsecure_base_url}}/", $coreData['base_url'], $val['value']);
  } else {
    $value = $val['value'];
  }
  $coreData[$path] = $value;
}

$query = "SELECT category_ids FROM nostress_export WHERE searchengine = 'clickaporter' AND enabled = 1;";
$arrCats = exec_query($query);
$categories = '';
if (is_array($arrCats) && (count($arrCats) > 0 && (isset($arrCats[0]['category_ids']) && (strlen(trim($arrCats[0]['category_ids'])) > 0)))) {
  $categories = trim($arrCats[0]['category_ids']);
}

// Busca dos produtos seguindo os seguintes critérios: configurável, com simples atribuído, com estoque e nas categorias específicas
$query = "SELECT DISTINCT conf.entity_id ";
$query.= "FROM ";
$query.= "  catalog_category_product AS cat INNER JOIN ";
$query.= "  catalog_product_entity AS conf ON (conf.entity_id = cat.product_id".(strlen($categories) > 0 ? " AND cat.category_id IN ($categories)" : "").") INNER JOIN ";
$query.= "  catalog_product_relation AS rel ON (rel.parent_id = conf.entity_id) INNER JOIN ";
$query.= "  cataloginventory_stock_item AS stk ON (stk.product_id = rel.child_id AND qty > 0) ";
$query.= "WHERE ";
$query.= "  conf.type_id = 'configurable';";

$products = exec_query($query);

// Inicia interações nos produtos selecionados para popular o objeto XML;
$count = 0;
foreach ($products AS $product) {
  $count++;
  $product_id = $product['entity_id'];

  // Recupera as informações do produto configurável
  $query = "SELECT ";
  $query.= "  ent.sku, ";
  $query.= "  url.value AS url, ";
  $query.= "  name.value AS nome, ";
  $query.= "  dsc.value AS descricao, ";
  $query.= "  'Hering' AS marca, ";
  $query.= "  price.price AS preco_de, ";
  $query.= "  CASE WHEN ( ";
  $query.= "      (sfr.value < NOW() AND (sto.value > NOW() OR sto.value IS NULL)) OR ";
  $query.= "      (sfr.value IS NULL AND sto.value > NOW()) OR ";
  $query.= "      (sfr.value IS NULL AND sto.value IS NULL) ";
  $query.= "    ) THEN price.final_price ";
  $query.= "    ELSE NULL ";
  $query.= "  END AS preco_por, ";
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
  $query.= "  LEFT JOIN catalog_product_index_price AS price ON ";
  $query.= "    (";
  $query.= "    price.entity_id = ent.entity_id AND ";
  $query.= "    price.customer_group_id = 1";
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
  $strCategoria = implode(' > ', $cats);

  // Recupera as informações relativas aos produtos simples
  $query = "SELECT cor.value AS color, img.value AS image, tam.value AS size ";
  $query.= "FROM ";
  $query.= "  catalog_product_entity_media_gallery AS img ";
  $query.= "  INNER JOIN catalog_product_relation AS rel ON (rel.parent_id = img.entity_id) ";
  $query.= "  INNER JOIN cataloginventory_stock_item AS stk ON (stk.product_id = rel.child_id and qty > 0) ";
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
  $query.= "WHERE ";
  $query.= "  img.entity_id = $product_id "; 
  $query.= "  AND img.value LIKE CONCAT('%',TRIM(val.value),'%');";

  $multiple = exec_query($query);

  $colors = $images = $sizes = Array();
  foreach ($multiple AS $each) {
    $colors[] = $each['color'];
    $images[] = $each['image'];
    $sizes[] = $each['size'];
  }

  // Monta efetivamente o XML
  $prodNode = $pData->addChild('roupa');

  addCdata($prodNode->addChild('id_produto'), trim($entityData['sku']));
  addCdata($prodNode->addChild('link_produto'), $coreData['base_url'] . trim($entityData['url']) . '?partner=&utm_source=clickaporter&utm_medium=' . trim(urlencode($entityData['nome'])));
  addCdata($prodNode->addChild('nome_produto'), trim($entityData['nome']));
  addCdata($prodNode->addChild('marca'), trim($entityData['marca']));
  addCdata($prodNode->addChild('categoria'), trim($strCategoria));
  addCdata($prodNode->addChild('descricao'), trim($entityData['descricao']));
  addCdata($prodNode->addChild('preco_de'), number_format($entityData['preco_de'], 2, ',', ''));
  if(trim($entityData['preco_por']) != trim($entityData['preco_de']) && ((int) $entityData['preco_por'] > 0)) addCdata($prodNode->addChild('preco_por'), number_format($entityData['preco_por'], 2, ',', ''));
  addCdata($prodNode->addChild('imagem'), $coreData['base_media_url'] . 'catalog/product' . trim($entityData['imagem']));

  if (is_array($images) && (count($images) > 0)) {
    $imagens = $prodNode->addChild('imagens');
    foreach (array_unique($images) AS $image) {
      addCdata($imagens->addChild('imagem'), $coreData['base_media_url'] . 'catalog/product' . trim($image));
    }
  }

  if (is_array($colors) && (count($colors) > 0)) {
    $cores = $prodNode->addChild('cores');
    foreach (array_unique($colors) AS $color) {
      addCdata($cores->addChild('cor'), trim($color));
    }
  }

  if (is_array($sizes) && (count($sizes) > 0)) {
    $tamanhos = $prodNode->addChild('tamanhos');
    foreach (array_unique($sizes) AS $size) {
      addCdata($tamanhos->addChild('tamanho'), trim($size));
    }
  }
}

// Recuperação de qualquer tipo de output que o script gerou
$buffer = ob_get_clean();

ob_start();
if (strlen($buffer) == 0) {
  $buffer = "[" . date("Y-m-d H:i:s") . "] Script executado com sucesso. gerado XML para $count produtos configuráveis em " . number_format((microtime() - $inicio_exec),3) . "s.\n";
} else {
  $mark = microtime();
  $buffer = "[".date("Y-m-d H:i:s")."] Foram encontrados os seguintes erros ao gerar o script: \nINICIO DO LOG $mark >>\n" . $buffer . "\n>> FIM do log $mark\n)\n"; 
}

// Escreve o output no arquivo de log
//$logdir = dirname(dirname(__FILE__)) . '../../var/log/parceiros';
$logdir = dirname(dirname(__FILE__)) . '/log';
if (!is_dir($logdir)) mkdir($logdir, 0777, true);
$file = fopen($logdir . '/clickaporter.log', 'a+');
fwrite($file, $buffer, strlen($buffer));
fclose($file);
ob_end_clean();

// Printa o arquivo XML
if (!headers_sent()) header('content-type: text/xml; charset=UTF-8');
echo $xml->asXML();

/*
// Grava o XML gerado no arquivo de integracao
$dom = new DOMDocument('1.0');
$dom->preserveWhiteSpace = false;
$dom->formatOutput = false;
$dom->loadXML($xml->asXML());

$filedir = dirname(dirname(dirname(__FILE__))) . '/media/virtualbiz/clickaporter';
if (!is_dir($logdir)) mkdir($logdir, 0777, true);
$dom->save("$filedir/clickaporter.xml");
*/
?>
