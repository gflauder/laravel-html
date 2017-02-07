<?php

namespace Spatie\Html;

use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;
use Spatie\Html\Exceptions\CannotRenderChild;
use Spatie\Html\Exceptions\InvalidHtml;
use Spatie\Html\Exceptions\MissingTag;
use Spatie\Html\Helpers\Arr;

abstract class BaseElement implements Htmlable, HtmlElement
{
    /** @var string */
    protected $tag;

    /** @var \Spatie\Html\Attributes */
    protected $attributes;

    /** @var array */
    protected $children = [];

    public function __construct()
    {
        if (empty($this->tag)) {
            throw MissingTag::onClass(static::class);
        }

        $this->attributes = new Attributes();
    }

    public static function create()
    {
        return new static();
    }

    /**
     * @param string $attribute
     * @param string $value
     *
     * @return static
     */
    public function attribute(string $attribute, string $value = '')
    {
        $element = clone $this;

        $element->attributes->setAttribute($attribute, $value);

        return $element;
    }

    /**
     * @param bool $condition
     * @param string $attribute
     * @param string $value
     *
     * @return static
     */
    public function attributeIf(bool $condition, string $attribute, string $value = '')
    {
        return $condition ?
            $this->attribute($attribute, $value) :
            $this;
    }

    /**
     * @param iterable $attributes
     *
     * @return static
     */
    public function attributes(iterable $attributes)
    {
        $element = clone $this;

        $element->attributes->setAttributes($attributes);

        return $element;
    }

    /**
     * @param string $attribute
     * @param string $value
     *
     * @return static
     */
    public function forgetAttribute(string $attribute)
    {
        $element = clone $this;

        $element->attributes->forgetAttribute($attribute);

        return $element;
    }

    /**
     * @param string $attribute
     * @param mixed $fallback
     *
     * @return mixed
     */
    public function getAttribute(string $attribute, string $fallback = '')
    {
        return $this->attributes->getAttribute($attribute, $fallback);
    }

    /**
     * @param iterable|string $class
     *
     * @return static
     */
    public function class($class)
    {
        return $this->addClass($class);
    }

    /**
     * Alias for `class`.
     *
     * @param iterable|string $class
     *
     * @return static
     */
    public function addClass($class)
    {
        $element = clone $this;

        $element->attributes->addClass($class);

        return $element;
    }

    /**
     * @param string $id
     *
     * @return static
     */
    public function id(string $id)
    {
        return $this->attribute('id', $id);
    }

    /**
     * Alias for `addChildren`.
     *
     * @param \Spatie\Html\HtmlElement|string|iterable|null $children
     * @param callable $mapper
     *
     * @return static
     */
    public function addChildren($children, callable $mapper = null)
    {
        if (is_null($children)) {
            return $this;
        }

        $element = clone $this;

        $children = Arr::create($children);

        $children = $mapper ? Arr::map($children, $mapper) : $children;

        $element->children = array_merge($this->children, $children);

        return $element;
    }

    /**
     * Alias for `addChildren`.
     *
     * @param \Spatie\Html\HtmlElement|string|iterable|null $children
     * @param callable $mapper
     *
     * @return static
     */
    public function children($children, callable $mapper = null)
    {
        return $this->addChildren($children, $mapper);
    }

    /**
     * @param \Spatie\Html\HtmlElement|string $child
     *
     * @return static
     */
    public function addChild($child)
    {
        $this->guardAgainstInvalidChild($child);

        $element = clone $this;

        $element->children[] = $child;

        return $element;
    }

    /**
     * @param \Spatie\Html\HtmlElement|string $child
     *
     * @return static
     */
    public function prependChild($child)
    {
        $this->guardAgainstInvalidChild($child);

        $element = clone $this;

        array_unshift($element->children, $child);

        return $element;
    }

    /**
     * @param string $text
     *
     * @return static
     */
    public function text(string $text)
    {
        return $this->html(htmlentities($text, ENT_QUOTES, 'UTF-8', false));
    }

    /**
     * @param string $html
     *
     * @return static
     */
    public function html(string $html)
    {
        if ($this->isVoidElement()) {
            throw new InvalidHtml("Can't set inner contents on `{$this->tag}` because it's a void element");
        }

        $element = clone $this;

        $element->children = [$html];

        return $element;
    }

    /**
     * Condintionally transform the element. Note that since elements are
     * immutable, you'll need to return a new instance from the callback.
     *
     * @param bool $condition
     * @param callable $callback
     */
    public function if(bool $condition, callable $callback)
    {
        return $condition ?
            $callback($this) :
            $this;
    }

    public function renderChildren(): Htmlable
    {
        $children = Arr::map($this->children, function ($child) {
            if ($child instanceof HtmlElement) {
                return $child->render();
            }

            if (is_string($child)) {
                return $child;
            }

            throw CannotRenderChild::childMustBeAnHtmlElementOrAString($child);
        });

        return new HtmlString(implode('', $children));
    }

    public function open(): Htmlable
    {
        return new HtmlString(
            $this->attributes->isEmpty()
                ? '<'.$this->tag.'>'
                : "<{$this->tag} {$this->attributes->render()}>"
        );
    }

    public function close(): Htmlable
    {
        return new HtmlString(
            $this->isVoidElement()
                ? ''
                : "</{$this->tag}>"
        );
    }

    public function render(): Htmlable
    {
        return new HtmlString(
            $this->open().$this->renderChildren().$this->close()
        );
    }

    public function isVoidElement(): bool
    {
        return in_array($this->tag, [
            'area', 'base', 'br', 'col', 'embed', 'hr',
            'img', 'input', 'keygen', 'link', 'menuitem',
            'meta', 'param', 'source', 'track', 'wbr',
        ]);
    }

    public function __clone()
    {
        $this->attributes = clone $this->attributes;
    }

    public function __toString(): string
    {
        return $this->render();
    }

    public function toHtml(): string
    {
        return $this->render();
    }

    protected function guardAgainstInvalidChild($child)
    {
        if ((! $child instanceof HtmlElement) && (! is_string($child))) {
            throw CannotRenderChild::childMustBeAnHtmlElementOrAString($child);
        }
    }
}
