<?php
/**
 * @brief 升级更新控制器
 */
class Update extends IController
{
	/**
	 * @brief iwebshop15122300 版本升级更新
	 */
	public function index()
	{
		$sql = array(
			"ALTER TABLE `{pre}address` CHANGE `default` `is_default` TINYINT( 1 ) NOT NULL DEFAULT '0' COMMENT '是否默认,0:为非默认,1:默认'",
			"ALTER TABLE `{pre}promotion` CHANGE `condition` `condition` TEXT NOT NULL COMMENT '活动生效条件 当type=0<消费额度>,当type=1<商品ID>,type=2<商品ID>'",
			"CREATE INDEX `type` ON `{pre}promotion` (`type`,`seller_id`)",
			"ALTER TABLE `{pre}promotion` ADD `sort` SMALLINT( 5 ) NOT NULL DEFAULT '99'",
			"ALTER TABLE `{pre}refundment_doc` ADD `order_goods_id` text COMMENT '订单与商品关联ID集合'",
		);

		$indexData = IDBFactory::getDB()->query( $this->_c("show index from {pre}promotion") );
		foreach($indexData as $key => $val)
		{
			if($val['Column_name'] == "condition")
			{
				IDBFactory::getDB()->query( $this->_c("ALTER TABLE `{pre}promotion` DROP INDEX `condition`") );
			}
		}

		foreach($sql as $key => $val)
		{
			IDBFactory::getDB()->query( $this->_c($val) );
		}

		$refundList = IDBFactory::getDB()->query( $this->_c("select * from {pre}refundment_doc") );
		foreach($refundList as $key => $val)
		{
			$where = " order_id = ".$val['order_id']." and goods_id = ".$val['goods_id']." and product_id = ".$val['product_id'];
			$orderGoodsList = IDBFactory::getDB()->query( $this->_c("select * from {pre}order_goods where ".$where) );
			if($orderGoodsList)
			{
				$temp = current($orderGoodsList);
				IDBFactory::getDB()->query( $this->_c("update {pre}refundment_doc set order_goods_id = ".$temp['id']." where id = ".$val['id']) );
			}
		}
		die("升级成功");
	}

	public function _c($sql)
	{
		return str_replace('{pre}',IWeb::$app->config['DB']['tablePre'],$sql);
	}
}