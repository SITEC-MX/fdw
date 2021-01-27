<?php
/**
 * Sistemas Especializados e Innovación Tecnológica, SA de CV
 * Mpsoft.FDW - Framework de Desarrollo Web para PHP
 *
 * v.2.0.0.0 - 2021-01-26
 */
namespace Mpsoft\FDW\Core;

abstract class Utilidades
{
    public static function ObtenerIPCliente():string
    {
        return isset($_SERVER["HTTP_CF_CONNECTING_IP"]) ? $_SERVER["HTTP_CF_CONNECTING_IP"] : $_SERVER['REMOTE_ADDR'];
    }
}