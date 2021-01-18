<?php
/**
 * Sistemas Especializados e Innovación Tecnológica, SA de CV
 * Mpsoft.FDW - Framework de Desarrollo Web para PHP
 *
 * v.2.0.0.0 - 2016-10-12
 */
namespace Mpsoft\FDW\Dato;

use Exception;

class BdDException extends Exception
{
    public function __construct ($message = "", $tipo_BDD="", $code = 0, $previous = NULL)
    {
        parent::__construct($message, $code, $previous);
    }
}
