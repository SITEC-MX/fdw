<?php
/**
 * Sistemas Especializados e Innovación Tecnológica, SA de CV
 * Mpsoft.FDW - Framework de Desarrollo Web para PHP
 *
 * v.2.0.0.0 - 2016-10-12
 */
namespace Mpsoft\FDW\Dato;

use Exception;

class ModuloException extends Exception
{
    public function __construct ($message = "", $tipo_Modulo="", $code = 0, $previous = NULL)
    {
        parent::__construct($message, $code, $previous);
    }
}
