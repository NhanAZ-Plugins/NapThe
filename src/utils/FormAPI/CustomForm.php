<?php

declare(strict_types=1);

namespace NapThe\utils\FormAPI;

use pocketmine\form\FormValidationException;
use function count;
use function gettype;
use function is_array;
use function is_bool;
use function is_float;
use function is_int;
use function is_string;

/**
 * Adapted from jojoe77777/FormAPI (LGPL-3.0).
 */
class CustomForm extends Form {
	/** @var list<int|string> */
	private array $labelMap = [];

	/** @var list<callable> */
	private array $validationMethods = [];

	public function __construct(?callable $callable) {
		parent::__construct($callable);
		$this->data["type"] = "custom_form";
		$this->data["title"] = "";
		$this->data["content"] = [];
	}

	public function processData(mixed &$data) : void {
		if ($data !== null && !is_array($data)) {
			throw new FormValidationException("Expected an array response, got " . gettype($data));
		}
		if (is_array($data)) {
			if (count($data) !== count($this->validationMethods)) {
				throw new FormValidationException("Expected an array response with the size " . count($this->validationMethods) . ", got " . count($data));
			}

			$new = [];
			foreach ($data as $i => $value) {
				$validationMethod = $this->validationMethods[$i] ?? null;
				if ($validationMethod === null) {
					throw new FormValidationException("Invalid element " . $i);
				}
				if (!$validationMethod($value)) {
					throw new FormValidationException("Invalid type given for element " . ($this->labelMap[$i] ?? $i));
				}
				$new[$this->labelMap[$i]] = $value;
			}
			$data = $new;
		}
	}

	public function setTitle(string $title) : self {
		$this->data["title"] = $title;
		return $this;
	}

	public function getTitle() : string {
		return $this->getStringField("title");
	}

	public function addLabel(string $text, string|int|null $label = null) : self {
		$this->addContent(["type" => "label", "text" => $text]);
		$this->labelMap[] = $label ?? count($this->labelMap);
		$this->validationMethods[] = static fn(mixed $value) : bool => $value === null;
		return $this;
	}

	public function addToggle(string $text, ?bool $default = null, string|int|null $label = null) : self {
		$content = ["type" => "toggle", "text" => $text];
		if ($default !== null) {
			$content["default"] = $default;
		}
		$this->addContent($content);
		$this->labelMap[] = $label ?? count($this->labelMap);
		$this->validationMethods[] = static fn(mixed $value) : bool => is_bool($value);
		return $this;
	}

	public function addSlider(string $text, int $min, int $max, int $step = -1, int $default = -1, string|int|null $label = null) : self {
		$content = ["type" => "slider", "text" => $text, "min" => $min, "max" => $max];
		if ($step !== -1) {
			$content["step"] = $step;
		}
		if ($default !== -1) {
			$content["default"] = $default;
		}
		$this->addContent($content);
		$this->labelMap[] = $label ?? count($this->labelMap);
		$this->validationMethods[] = static fn(mixed $value) : bool => (is_float($value) || is_int($value)) && $value >= $min && $value <= $max;
		return $this;
	}

	/**
	 * @param list<string> $steps
	 */
	public function addStepSlider(string $text, array $steps, int $defaultIndex = -1, string|int|null $label = null) : self {
		$content = ["type" => "step_slider", "text" => $text, "steps" => $steps];
		if ($defaultIndex !== -1) {
			$content["default"] = $defaultIndex;
		}
		$this->addContent($content);
		$this->labelMap[] = $label ?? count($this->labelMap);
		$this->validationMethods[] = static fn(mixed $value) : bool => is_int($value) && isset($steps[$value]);
		return $this;
	}

	/**
	 * @param list<string> $options
	 */
	public function addDropdown(string $text, array $options, ?int $default = null, string|int|null $label = null) : self {
		$content = ["type" => "dropdown", "text" => $text, "options" => $options];
		if ($default !== null) {
			$content["default"] = $default;
		}
		$this->addContent($content);
		$this->labelMap[] = $label ?? count($this->labelMap);
		$this->validationMethods[] = static fn(mixed $value) : bool => is_int($value) && isset($options[$value]);
		return $this;
	}

	public function addInput(string $text, string $placeholder = "", ?string $default = null, string|int|null $label = null) : self {
		$content = ["type" => "input", "text" => $text, "placeholder" => $placeholder];
		if ($default !== null) {
			$content["default"] = $default;
		}
		$this->addContent($content);
		$this->labelMap[] = $label ?? count($this->labelMap);
		$this->validationMethods[] = static fn(mixed $value) : bool => is_string($value);
		return $this;
	}

	/**
	 * @param array<string, mixed> $content
	 */
	private function addContent(array $content) : self {
		$items = $this->data["content"] ?? null;
		if (!is_array($items)) {
			throw new \LogicException("Invalid custom form content");
		}
		$items[] = $content;
		$this->data["content"] = $items;
		return $this;
	}
}
