<?php

namespace Palmtree\Form\Captcha;

use Palmtree\Form\Form;
use Palmtree\Html\Element;

class HoneypotCaptcha extends AbstractCaptcha implements CaptchaInterface
{
    public function verify($response)
    {
        return empty($response);
    }

    public function getErrorMessage()
    {
        return 'This is a honeypot field and should be left blank.';
    }

    public function getElements(Element $element, Form $form)
    {
        $elements = [];

        unset($element->attributes['placeholder']);

        $element->attributes->add([
            'type'         => 'text',
            'style'        => 'display: none;',
            'autocomplete' => 'off',
        ]);

        $elements[] = $element;

        return $elements;
    }
}
