<?php


class RscDtgs_Http_Parameters extends RscDtgs_Common_Collection
{
	public function get_esc_html($key, $default = null)
	{
		$default = parent::get($key, $default);
		$default = esc_html($default);

		return $default;
	}
} 