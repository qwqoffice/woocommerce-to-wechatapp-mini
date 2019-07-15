<?php
/**
 * 
 * 微信支付API异常类
 * @author widyhu
 *
 */
class W2W_WxPayException extends Exception {
	
	public function errorMessage() {
		
		return $this->getMessage();
	}
}
