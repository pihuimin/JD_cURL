<?php
function getTime()
{
	$time = microtime();
	$time = explode(' ', $time);
	return (double)((double)$time[1] + (double)$time[0]);
}


$strtime = getTime();


// 使用CURL发GET请求
function curl_get($url)
{
	static $obj = NULL;
	if($obj === NULL)
		// 创建对象
		$obj = curl_init();
	// 配置对象
	curl_setopt($obj, CURLOPT_URL, $url);
	// 获取返回值
	curl_setopt($obj, CURLOPT_RETURNTRANSFER, 1);
	// 执行请求
	$str = curl_exec($obj);
	return $str;
}

//$url = 'http://p.3.cn/prices/mgets?callback=jQuery3605824&type=1&area=1_2901_4135_0&skuIds=J_1068461%2CJ_1748535%2CJ_1666718%2CJ_1412531%2CJ_1221393&_=1443254973547';
//echo urldecode($url);die;

//$str = '[{"id":"J_751624","p":"1398.00","m":"2099.00"},{"id":"J_1032696","p":"1399.00","m":"2099.00"}]';

//$str = json_decode($str);

//var_dump($str);die;

$price_api = 'http://p.3.cn/prices/mgets?&callback=jQuery6460172&my=list_price&type=1&area=1_2901_4135_0&skuIds=';
set_time_limit(0);  // 设置脚本一直执行到结束【PHP默认一个脚本只能执行30秒】
mysql_connect('localhost', 'root', '');
mysql_query('SET NAMES UTF8');
mysql_select_db('php38');
$str = curl_get('http://list.jd.com/list.html?cat=737,794,878&page=1');
//var_dump($str);
$page_re = '/共<b>(\\d+)<\/b>页/';
preg_match($page_re, $str, $ret);
$totalPage = $ret[1];
// 每件商品的LI标签
// U : 非贪婪
// s : .代表包含换行等特殊符号的任意字符
$_li = '/<li class="gl-item">(.+)<\/li>/Us';
// 取商品的图片
$_img = '/<img.+data-lazy-img="(.+)".+>/Us';
// 取名称
$_title = '/<em>([^¥]+)<\/em>/Us';
// 取价格
$_price = '/<i>(.+)<\/i>/Us';
$_id = '/data-sku="(\d+)"/Us';
for ($i=1; $i<=$totalPage; $i++)
{
	$str = curl_get('http://list.jd.com/list.html?cat=737,794,878&page='.$i);
	preg_match_all($_li, $str, $li);
	$goods_id = array();
	// 再循环每个LI取出商品的图片、价格、名称
	foreach ($li[1] as $k => $v)
	{
		//file_put_contents('./price.html', $v);
		//var_dump($v);die;
		/********* 抓取图片 ********/
		preg_match($_img, $v, $img);
		// 下载图片
		$imgCode = curl_get('http:'.$img[1]);
		// 写到本地硬盘
		$newImgName = 'jdimage/'.uniqid().'.jpg';
		file_put_contents('./Public/Uploads/'.$newImgName, $imgCode);
		/********* 抓取名称 ********/
		preg_match($_title, $v, $title);
		/********* 抓取价格 ********/
		//preg_match($_price, $v, $price);
		//var_dump($price);die;
		if(isset($title[1]))
		{
			mysql_query('INSERT INTO php38_goods(cat_id,goods_name,shop_price,market_price,sm_logo,logo,mid_logo) VALUES("0","'.$title[1].'",100,100,"'.$newImgName.'","'.$newImgName.'","'.$newImgName.'")');
			$newId = mysql_insert_id();
			// 抓取出商品的ID
			preg_match($_id, $v, $id);
			$goods_id[] = array($id[1], $newId);
		}
	}
	
	//var_dump($goods_id);die;
	
	$J_arr = array();
	foreach ($goods_id as $k => $v)
	{
		$J_arr[] = 'J_'.$v[0];
	}
	$J_arr = implode(',', $J_arr);
	$price = curl_get($price_api.$J_arr);
	$price = str_replace('jQuery6460172(', '', $price);
	$price = str_replace(');', '', $price);
	//file_put_contents('./price.html', $price);die;
	$price = json_decode($price);
	//var_dump($price);die;
	foreach ($price as $k => $v)
	{
		mysql_query('UPDATE php38_goods SET shop_price="'.$v->p.'" WHERE id = '.$goods_id[$k][1]);
	}
	
	$endtime = getTime();
	echo $endtime - $strtime;
	die;
	// 每采集一页，休息一下，给些时间让系统把内存中的图片写到硬盘
	sleep(8);
}
