<?php
/**-
 * 省市区地址分割
 * 将 浙江省杭州市江干区XX路X号 分割成 浙江省 杭州市 江干区 XX路X号
 * User: zmxfree@gmail.com
 * Date: 14-6-18
 * Time: 下午12:52
 */

/**此引入文件应包含全国所有的省市区信息
 * 文件格式为多维数组，键是地名，值可以赋一个编号，供回查
 * array(
 * [0] => array('浙江省' => '1','北京市' => '2','上海市' => '3',...),
 * [1] => array('杭州市' => '1-1','宁波市' => '1-2','市辖区（一般的直辖市会分为市辖区和周边地区）' => '2-1,3-1',...),
 * [3] => array('西湖区'  => '1-1-1','江干区' => '1-1-2','海淀区'=>'2-1-1',...),
 * [4] => array('可按需求添加城镇信息')
 * )
 * 这样的结构CRUD操作很方便，不需要严格按照省市区结构分配。
 */
$address = include('address.info.php');
$cache_file = 'lenarr.cache.php';
mb_internal_encoding('utf8');
$len_arr = array();
if (is_file($cache_file) && is_readable($cache_file)) {
	//读取省市区长度缓存
	$len_arr = include($cache_file);
} else {
	$fp = fopen($cache_file, 'w');
	//省市区的数量有很多，但长度却是有限的，直接计算出长度，用长度去匹配，大大减少匹配次数
	$len_arr[] = array_values(array_unique(array_map('mb_strlen', array_keys($address[0])))); //所有省的长度
	$len_arr[] = array_values(array_unique(array_map('mb_strlen', array_keys($address[1])))); //市的长度
	$len_arr[] = array_values(array_unique(array_map('mb_strlen', array_keys($address[2])))); //区的长度
	//保存文件缓存
	$result = fwrite($fp, '<?php return ' . var_export($len_arr, true) . ';?>');
	fclose($fp);
}

//读取要分割的地址
$f = './address.log';
$fstr = is_file($f) && is_readable($f) ? file_get_contents($f) : '';
$add_arr = explode("\n", $fstr);

if (is_array($add_arr)) {
	foreach ($add_arr as $addr) {
		//初始化
		$l = 0;
		$i = 0;
		$p = 0;
		$find = false;
		$arr_get = array();
		$addr = trim($addr);

		while (!$find) {
			//判断是否超出lenarr数组的长度
			if (!isset($len_arr[$l])) {
				$arr_get[] = mb_substr($addr, $p, null);
				$find = true;
				break;
			}

			//截取地址
			$ad = mb_substr($addr, $p, $len_arr[$l][$i]);
			//匹配，匹配到就进入下一层级即$l++
			if (isset($address[$l][$ad])) {
				$arr_get[] = $ad; //存储值
				$p += $len_arr[$l][$i];
				$i = 0;
				$l++;
				continue;
			}
			$i++;

			//判断当前层级是否循环完毕
			//当前层级循环完毕仍未匹配到，则循环下一层级，一般是直辖市比如北京市海淀区这种情况，或者是信息不全
			if (isset($len_arr[$l]) && $i >= count($len_arr[$l])) {
				echo $ad . '<br/>'; //记录下来
				$i = 0;
				$l++;
				continue;
			}
		}
		//分割好的地址写入文件
		file_put_contents('address.detail', implode("\t", $arr_get) . "\n", FILE_APPEND);
	}
}