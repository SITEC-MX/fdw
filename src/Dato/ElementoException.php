<?php
/**
 * Sistemas Especializados e Innovación Tecnológica, SA de CV
 * Mpsoft.FDW - Framework de Desarrollo Web para PHP
 *
 * v.2.0.0.0 - 2016-10-12
 */
namespace Mpsoft\FDW\Dato;

use Exception;
use \Throwable;

class ElementoException extends Exception
{
    public function __construct (string $message = "", string $tipo_Elemento="", int $code = 0, Throwable $previous = NULL)
    {
        parent::__construct($message, $code, $previous);
    }
}
