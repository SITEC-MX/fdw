<?php
/**
 * Sistemas Especializados e Innovación Tecnológica, SA de CV
 * Mpsoft.FDW - Framework de Desarrollo Web para PHP
 *
 * v.2.0.0.0 - 2016-11-06
 */
namespace Mpsoft\FDW\Core;

use Mpsoft\FDW\Dato\BdD;

/**
 * Utilidades de uso general para FDW
 */
abstract class Parametro
{
    public static function ObtenerGet($nombre, $tipo = FDW_DATO_STRING)
    {
        return Parametro::Obtener($nombre, true, $tipo);
    }

    public static function ObtenerPost($nombre, $tipo = FDW_DATO_STRING)
    {
        return Parametro::Obtener($nombre, false, $tipo);
    }

    public static function Obtener($nombre, $esGet = true, $tipo = FDW_DATO_STRING)
    {
        $valor = null;

        if ($esGet)
        {
            if (isset($_GET[$nombre]))
            {
                $valor = $_GET[$nombre];
            }
        }
        else
        {
            if (isset($_POST[$nombre]))
            {
                $valor = $_POST[$nombre];
            }
        }

        return Parametro::LimpiarValor($valor, $tipo);
    }

    public static function LimpiarValor($valor, $tipo = FDW_DATO_STRING)
    {
        return BdD::ConvertirATipoDeDato($valor, $tipo);
    }
}
