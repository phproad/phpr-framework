<?php namespace Db;

class Form_Section extends Form_Element
{
	public $title;
	public $description;
	public $html_id;
	public $content_html = false;

	public function __construct($title, $description, $html_id = null)
	{
		$this->title = $title;
		$this->description = $description;
		$this->html_id = $html_id;
	}

	public function is_html($content_html = true)
	{
		$this->content_html = $content_html;
		return $this;
	}
}
