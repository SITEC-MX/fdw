<?php
/**
 * Sistemas Especializados e Innovación Tecnológica, SA de CV
 * Mpsoft.FDW - Framework de Desarrollo Web para PHP
 *
 * v.2.0.0.0 - 2016-10-12
 */
namespace Mpsoft\FDW\Dato;

/**
 * Encapsula el resultado de una consulta sobre la base de datos
 */
abstract class ResultadoDeConsulta
{
    /**
     * Obtiene el siguiente registro devuelto por la consulta
     * @return mixed Retorna un arreglo (si $incluirCampos es false) ó un arreglo asociativo (si $incluirCampos es true) con el siguiente resultado o null si no hay más resultados que retornar.
     */
    public abstract function ObtenerSiguienteResultado();

    /**
     * Libera el resultado generado por la consulta
     */
    public abstract function LiberarResultado();
}