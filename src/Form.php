<?php

declare(strict_types=1);

namespace Palmtree\Form;

use Palmtree\ArgParser\ArgParser;
use Palmtree\Form\Exception\AlreadySubmittedException;
use Palmtree\Form\Exception\NotSubmittedException;
use Palmtree\Form\Exception\OutOfBoundsException;
use Palmtree\Form\Type\TypeInterface;
use Palmtree\Html\Element;
use Palmtree\NameConverter\SnakeCaseToCamelCaseNameConverter;

class Form
{
    /** @var string */
    protected $key;
    /** @var array<string, TypeInterface> */
    protected $fields = [];
    /** @var bool */
    protected $ajax = false;
    /** @var bool */
    protected $submitted = false;
    /** @var bool|null */
    protected $valid;
    /** @var string */
    protected $method = 'POST';
    /** @var string|null */
    protected $action;
    /** @var string|null */
    protected $encType;
    /** @var array<string, string> */
    protected $errors = [];
    /** @var string */
    protected $fieldWrapper = 'div.form-group.mb-3';
    /** @var string */
    protected $invalidElementSelector = 'div.invalid-feedback.small';
    /** @var bool */
    protected $htmlValidation = true;
    /** @var FormRenderer */
    protected $renderer;

    protected const REQUESTED_WITH_HEADER = 'HTTP_X_REQUESTED_WITH';

    /**
     * @param array|string $args
     */
    public function __construct($args = [])
    {
        $this->parseArgs($args);
        $this->renderer = new FormRenderer($this);
    }

    public function renderStart(): string
    {
        return $this->renderer->renderStart();
    }

    public function renderEnd(bool $renderRest = true): string
    {
        return $this->renderer->renderEnd($renderRest);
    }

    public function render(): string
    {
        return $this->renderer->render();
    }

    public function renderField(string $name): string
    {
        return $this->renderer->renderField($name);
    }

    public function isValid(): bool
    {
        if (!$this->submitted) {
            throw new NotSubmittedException('Form must be submitted before calling ' . __METHOD__);
        }

        if ($this->valid === null) {
            $this->valid = true;
            foreach ($this->fields as $field) {
                if (!$field->isValid()) {
                    $this->valid = false;
                    if ($errorMessage = $field->getErrorMessage()) {
                        $this->addError($field->getName(), $errorMessage);
                    }
                }
            }
        }

        return $this->valid;
    }

    public function submit(array $data): void
    {
        if ($this->submitted) {
            throw new AlreadySubmittedException(__METHOD__ . ' can only be called once');
        }

        $this->submitted = true;

        foreach ($this->fields as $field) {
            $key = $field->getName();

            if (isset($data[$key]) || \array_key_exists($key, $data)) {
                $field->setData($data[$key]);
            }

            $field->build();
            $field->mapData();
        }
    }

    public function handleRequest(): void
    {
        $requestData = $this->getRequestData();

        if (!isset($requestData[$this->key])) {
            return;
        }

        $data = [];

        foreach ($requestData[$this->key] ?? [] as $key => $value) {
            $data[$key] = $value;
        }

        foreach ($_FILES[$this->key] ?? [] as $key => $parts) {
            foreach ($parts as $name => $value) {
                $data[$name][$key] = $value;
            }
        }

        $this->submit($data);
    }

    public function getRequestData(): array
    {
        return strtoupper($this->method) === 'POST' ? $_POST : $_GET;
    }

    public function setKey(string $key): self
    {
        $this->key = "form_$key";

        return $this;
    }

    public function getKey(): string
    {
        return $this->key;
    }

    public function setAjax(bool $ajax): self
    {
        $this->ajax = $ajax;

        return $this;
    }

    public function isAjax(): bool
    {
        return $this->ajax;
    }

    public static function isAjaxRequest(): bool
    {
        return strtolower($_SERVER[self::REQUESTED_WITH_HEADER] ?? '') === 'xmlhttprequest';
    }

    public function isSubmitted(): bool
    {
        return $this->submitted;
    }

    public function setMethod(string $method): self
    {
        $this->method = strtoupper($method);

        return $this;
    }

    public function setAction(string $action): self
    {
        $this->action = $action;

        return $this;
    }

    public function setEncType(string $encType): self
    {
        $this->encType = $encType;

        return $this;
    }

    /**
     * @return array<string, string>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * @param array<string, string> $errors
     */
    public function setErrors(array $errors): self
    {
        $this->errors = $errors;

        return $this;
    }

    public function addError(string $fieldName, string $errorMessage): self
    {
        $this->errors[$fieldName] = $errorMessage;

        return $this;
    }

    /**
     * @return array<string, TypeInterface>
     */
    public function all(): array
    {
        return $this->fields;
    }

    public function has(string $name): bool
    {
        return isset($this->fields[$name]);
    }

    public function get(string $name): TypeInterface
    {
        if (!$this->has($name)) {
            throw new OutOfBoundsException("Field with key '$name' does not exist");
        }

        return $this->fields[$name];
    }

    public function add(TypeInterface ...$fields): self
    {
        foreach ($fields as $field) {
            $field->setForm($this);

            $this->fields[$field->getName()] = $field;
        }

        return $this;
    }

    public function hasHtmlValidation(): bool
    {
        return $this->htmlValidation;
    }

    public function setHtmlValidation(bool $htmlValidation): self
    {
        $this->htmlValidation = $htmlValidation;

        return $this;
    }

    public function getAction(): ?string
    {
        return $this->action;
    }

    public function hasRequiredField(): bool
    {
        foreach ($this->fields as $field) {
            if ($field->isRequired()) {
                return true;
            }
        }

        return false;
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    public function setFieldWrapper(string $fieldWrapper): self
    {
        $this->fieldWrapper = $fieldWrapper;

        return $this;
    }

    public function getFieldWrapper(): string
    {
        return $this->fieldWrapper;
    }

    public function getEncType(): ?string
    {
        return $this->encType;
    }

    public function setInvalidElementSelector(string $invalidElementSelector): void
    {
        $this->invalidElementSelector = $invalidElementSelector;
    }

    public function getInvalidElementSelector(): string
    {
        return $this->invalidElementSelector;
    }

    public function createInvalidElement(): Element
    {
        $element = new Element($this->invalidElementSelector);

        $element->classes[] = 'palmtree-invalid-feedback';

        return $element;
    }

    /**
     * @return array<string, TypeInterface>
     */
    public function getFields(): array
    {
        return $this->fields;
    }

    /**
     * @param string|array $args
     */
    private function parseArgs($args): void
    {
        $parser = new ArgParser($args, 'key', new SnakeCaseToCamelCaseNameConverter());

        $parser->parseSetters($this);
    }

    public function __toString(): string
    {
        return $this->render();
    }
}
