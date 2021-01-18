<?php
/**
 * Sistemas Especializados e Innovación Tecnológica, SA de CV
 * Mpsoft.FDW - Framework de Desarrollo Web para PHP
 *
 * v.2.0.0.0 - 2016-10-12
 */
namespace Mpsoft\FDW\Dato\MySQL;

/**
 * Encapsula el resultado de una consulta sobre la base de datos MySQL
 */
class ResultadoDeConsulta extends \Mpsoft\FDW\Dato\ResultadoDeConsulta
{
    private $mysql_result = null;

    public function __construct($mysql_result)
    {
        $this->mysql_result = $mysql_result;
    }

    /**
     * Obtiene el siguiente registro devuelto por la consulta
     * @return mixed Retorna un arreglo (si $incluirCampos es false) ó un arreglo asociativo (si $incluirCampos es true) con el siguiente resultado o null si no hay más resultados que retornar.
     */
    public function ObtenerSiguienteResultado()
    {
        return $this->mysql_result->fetch_array(MYSQLI_ASSOC);
    }

    /**
     * Libera el resultado generado por la consulta
     */
    public function LiberarResultado()
    {
        $this->mysql_result->close();
    }
}