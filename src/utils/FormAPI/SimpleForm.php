<?php

declare(strict_types=1);

namespace Donate\utils\FormAPI;

use pocketmine\form\FormValidationException;
use function count;
use function gettype;
use function is_int;

/**
 * Adapted from jojoe77777/FormAPI (LGPL-3.0).
 */
class SimpleForm extends Form {
	public const IMAGE_TYPE_PATH = 0;
	public const IMAGE_TYPE_URL = 1;

	/** @var list<int|string|null> */
	private array $labelMap = [];

	public function __construct(?callable $callable) {
		parent::__construct($callable);
		$this->data["type"] = "form";
		$this->data["title"] = "";
		$this->data["content"] = "";
		$this->data["buttons"] = [];
	}

	public function processData(mixed &$data) : void {
		if ($data !== null) {
			if (!is_int($data)) {
				throw new FormValidationException("Expected an integer response, got " . gettype($data));
			}
			$count = count($this->data["buttons"]);
			if ($data >= $count || $data < 0) {
				throw new FormValidationException("Button $data does not exist");
			}
			$data = $this->labelMap[$data] ?? null;
		}
	}

	public function setTitle(string $title) : self {
		$this->data["title"] = $title;
		return $this;
	}

	public function getTitle() : string {
		return $this->data["title"];
	}

	public function getContent() : string {
		return $this->data["content"];
	}

	public function setContent(string $content) : self {
		$this->data["content"] = $content;
		return $this;
	}

	public function addButton(string $text, int $imageType = -1, string $imagePath = "", int|string|null $label = null) : self {
		$content = ["text" => $text];
		if ($imageType !== -1) {
			$content["image"]["type"] = $imageType === self::IMAGE_TYPE_PATH ? "path" : "url";
			$content["image"]["data"] = $imagePath;
		}
		$this->data["buttons"][] = $content;
		$this->labelMap[] = $label ?? count($this->labelMap);
		return $this;
	}
}
