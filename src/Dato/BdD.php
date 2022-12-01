<?php
/**
 * Sistemas Especializados e Innovación Tecnológica, SA de CV
 * Mpsoft.FDW - Framework de Desarrollo Web para PHP
 *
 * v.2.0.0.0 - 2016-10-12
 */
namespace Mpsoft\FDW\Dato;

use \DateTime;
use \Exception;

/**
 * Clase genérica para realizar operaciones sobre una base de datos
 */
abstract class BdD
{
    /**
     * Parámetros de conexión a la base de datos.
     * @var object Objeto que contiene los parámetros de conexión a la base de datos (bdd_servidor, bdd_usuario, bdd_contrasena, bdd_bdd).
     */
    protected $parametrosDeConexion;

    /**
     * Bandera que indica si se ha establecido una conexión o no con el servidor de base de datos.
     * @var boolean Cuando es true indica que se ha establecido conexión con el servidor de base de datos.
     */
    protected $conectado = false;

    /**
     * Constructor del objeto de base de datos
     * @param object $parametrosDeConexion Objeto con los parámetros de conexión a la base de datos (bdd_servidor, bdd_usuario, bdd_contrasena, bdd_bdd).
     */
    public function __construct($parametrosDeConexion)
    {
        global $CFG;

        if($parametrosDeConexion) // Si se proporcionan los parámetros de conexión
        {
            if(is_object($parametrosDeConexion)) // Si los parámetros son un objeto
            {
                // Si el objeto contiene la información necesaria
                if( isset($parametrosDeConexion->bdd_servidor) && isset($parametrosDeConexion->bdd_usuario) && isset($parametrosDeConexion->bdd_contrasena) && isset($parametrosDeConexion->bdd_bdd) )
                {
                    $this->parametrosDeConexion = $parametrosDeConexion;
                }
                else // Si el objeto contiene la información necesaria
                {
                    throw new BdDException("No se han proporcionado todos los parámetros necesarios para realizar la conexión.");
                }
            }
            else // Si los parámetros NO son un objeto
            {
                throw new BdDException("Se esperaba un objeto con los parámetros de conexión.");
            }
        }
        else // Si no se proporcionan los parámetros de conexión
        {
            throw new BdDException("No se proporcionó el objeto con los parámetros de conexión.");
        }
    }

    /**
     * Establece una conexión con el servidor de base de datos.
     * @return boolean Retorna true si se ha establecido una conexión con el servidor de datos, de lo contrario retorna false.
     */
    public abstract function Conectar();

    /**
     * Verifica si se ha establecido una conexión con el servidor de datos.
     * @return boolean Retorna true si se ha establecido una conexión con el servidor de datos, de lo contrario retorna false.
     */
    public function HayConexion()
    {
        return $this->conectado;
    }

    /**
     * Ejecuta una consulta SELECT sobre la base de datos.
     * @param string $tabla Nombre de la tabla sobre la cual se seleccionarán los datos. "Tabla" creará el alias t
     * @param array $campos Arreglo con el listado de campos a seleccionar. array("Campo1", "Campo2")
     * @param array $filtros Arreglo asociativo de arreglo de arreglos con la información de los filtro a aplicar. array( "Campo1" => array( array("tipo"=> ** Ver lógica ** (opcional, se asume FDW_DATO_BDD_LOGICA_Y), "operador"=> ** Ver operadores **, "operando"=>Valor), array("operador"=> ** Ver operadores **, "operando"=>Valor) ) )
     * @param array $ordenamiento Arreglo con los campos que se utilizarán para ordenar la consulta. array("Campo1", "Campo2 DESC")
     * @param int $inicioONumeroDeRegistros Si se especifica $numeroDeRegistros es el registro inicial que se seleccionará, de lo contrario es el total de registros a seleccionar.
     * @param int $numeroDeRegistros Total de registros a seleccionar.
     * @param array $enlaces Arreglo asociativo de arreglos con la información de enlaces a otras tablas. array( "Selector" => array("tipo"=> "INNER|LEFT|...", "tabla"=>"x", "filtro"=> ** Ver campo $filtro ** ) )
     * @param array $agrupamiento Arreglo con los campos que se utilizarán para agrupar la consulta. array("Campo1", "Campo2")
     * @return ResultadoDeConsulta Regresa el resultado de la consulta ejecutada.
     */
    public abstract function EjecutarSELECT($tabla, $campos, $filtros = array(), $ordenamiento = array(), $inicioONumeroDeRegistros = 0, $numeroDeRegistros = 0, $enlaces = array(), $agrupamiento = array());

    /**
     * Construye una consulta SELECT sobre la base de datos.
     * @param string $tabla Nombre de la tabla sobre la cual se seleccionarán los datos. "Tabla" creará el alias t
     * @param array $campos Arreglo con el listado de campos a seleccionar. array("Campo1", "Campo2")
     * @param array $filtros Arreglo asociativo de arreglo de arreglos con la información de los filtro a aplicar. array( "Campo1" => array( array("tipo"=> ** Ver lógica ** (opcional, se asume FDW_DATO_BDD_LOGICA_Y), "operador"=> ** Ver operadores **, "operando"=>Valor), array("operador"=> ** Ver operadores **, "operando"=>Valor) ) )
     * @param array $ordenamiento Arreglo con los campos que se utilizarán para ordenar la consulta. array("Campo1", "Campo2 DESC")
     * @param int $inicioONumeroDeRegistros Si se especifica $numeroDeRegistros es el registro inicial que se seleccionará, de lo contrario es el total de registros a seleccionar.
     * @param int $numeroDeRegistros Total de registros a seleccionar.
     * @param array $enlaces Arreglo asociativo de arreglos con la información de enlaces a otras tablas. array( "Selector" => array("tipo"=> "INNER|LEFT|...", "tabla"=>"x", "filtro"=> ** Ver campo $filtro ** ) )
     * @param array $agrupamiento Arreglo con los campos que se utilizarán para agrupar la consulta. array("Campo1", "Campo2")
     * @return array Regresa un arreglo con la consulta separada por parámetros.
     */
    public abstract function ConstruirSELECT($tabla, $campos, $filtros = array(), $ordenamiento = array(), $inicioONumeroDeRegistros = 0, $numeroDeRegistros = 0, $enlaces = array(), $agrupamiento = array());

    /**
     *
     * @param array $filtros Arreglo asociativo de arreglo de arreglos con la información de los filtro a aplicar. array( "Campo1" => array( array("tipo"=> "AND|OR" (opcional, se asume AND), "operador"=> ** Ver operadores **, "operando"=>Valor), array("operador"=> ** Ver operadores **, "operando"=>Valor) ) )
     */
    public abstract function ConstruirWHERE($filtros);

    /**
     * Ejecuta una consulta INSERT sobre la base de datos.
     * @param string $tabla Nombre de la tabla sobre la cual se insertarán los datos.
     * @param array $campos Arreglo con el listado de campos, correspondientes al arreglo $valores. array("Campo1", "Campo2")
     * @param array $valores Valores de los campos que se agregarán a la base de datos.
     * @return int Regresa el ID del registro creado.
     */
    public abstract function EjecutarINSERT($tabla, $campos, $valores);

    /**
     * Ejecuta una consulta UPDATE sobre la base de datos.
     * @param string $tabla Nombre de la tabla sobre la cual se modificarán los datos.
     * @param array $campos Arreglo con el listado de campos, correspondientes al arreglo $valores. array("Campo1", "Campo2")
     * @param array $valores Valores de los campos que se modificarán en la base de datos.
     * @param array $filtros Arreglo asociativo de arreglo de arreglos con la información de los filtro a aplicar. array( "Campo1" => array( array("tipo"=> ** Ver lógica ** (opcional, se asume FDW_DATO_BDD_LOGICA_Y), "operador"=> ** Ver operadores **, "operando"=>Valor), array("operador"=> ** Ver operadores **, "operando"=>Valor) ) )
     * @return boolean Regresa true en caso de éxito.
     */
    public abstract function EjecutarUPDATE($tabla, $campos, $valores, $filtros = array());

    /**
     * Ejecuta una consulta DELETE sobre la base de datos.
     * @param string $tabla Nombre de la tabla sobre la cual se eliminarán los datos.
     * @param array $filtros Arreglo asociativo de arreglo de arreglos con la información de los filtro a aplicar. array( "Campo1" => array( array("tipo"=> ** Ver lógica ** (opcional, se asume FDW_DATO_BDD_LOGICA_Y), "operador"=> ** Ver operadores **, "operando"=>Valor), array("operador"=> ** Ver operadores **, "operando"=>Valor) ) )
     */
    public abstract function EjecutarDELETE($tabla, $filtros = array());

    /**
     * Inicia una transacción en la base de datos.
     */
    public abstract function IniciarTransaccion();

    /**
     * Confirma los cambios realizados a la base de datos.
     */
    public abstract function RealizarCommit();

    /**
     * Cancela los cambios realizados a la base de datos.
     */
    public abstract function RealizarRollback();



    public static function ConvertirATipoDeDato($valor, $tipoDeDato)
    {
        switch($tipoDeDato)
        {
            case FDW_DATO_BOOL:
                $valor = filter_var($valor, FILTER_VALIDATE_BOOLEAN);
                break;

            case FDW_DATO_INT:
                $valor = $valor !== null ? (int)$valor : null;
                break;

            case FDW_DATO_FLOAT:
            case FDW_DATO_DOUBLE:
                $valor = $valor !== null ? floatval($valor) : null;
                break;

            case FDW_DATO_STRING:
                if(!is_null($valor)) // Si hay algún valor que convertir
                {
                    if( !is_string($valor) ) // Si el valor no es string
                    {
                        $tipoVariable = gettype($valor);
                        switch($tipoVariable)
                        {
                            case "boolean": $valor = $valor ? "true" : "false"; break;

                            case "double":
                            case "integer":  $valor = (string)$valor; break;

                            case "object":
                                if( is_a($valor, DateTime::class) ) // Si el valor no es un DateTime
                                {
                                    $valor = $valor->format("Y-m-d H:i:s"); break;
                                }
                                else
                                {
                                    throw new Exception("El tipo de objeto de origen '" . get_class($valor) . "' no está soportado.");
                                }
                                break;

                            default:
                                throw new Exception("El tipo de dato de origen '{$tipoVariable}' no está soportado.");
                        }
                    }
                }
                break;

            case FDW_DATO_DATE:
            case FDW_DATO_DATETIME:
            case FDW_DATO_TIME:
                if(!is_null($valor)) // Si hay algún valor que convertir
                {
                    if( !is_a($valor, DateTime::class) ) // Si el valor no es un DateTime
                    {
                        if( is_string($valor) ) // Si el valor es string
                        {
                            $valor = new DateTime($valor);

                            if($tipoDeDato == FDW_DATO_DATE) // Si se está convirtiendo a fecha
                            {
                                $valor->setTime(0,0,0,0);
                            }
                        }
                        else // Si el valor no es string
                        {
                            throw new Exception("No es posible convertir el valor a Date o DateTime");
                        }
                    }
                }
                break;
        }

        return $valor;
    }

    public static function ConvertirAString($valor)
    {
        $valor_string = NULL;

        switch(gettype($valor))
        {
            case "object":
                switch(get_class($valor))
                {
                    case DateTime::class:
                        $valor_string = $valor->format("Y-m-d H:i:s");
                        break;

                    default:
                        throw new Exception("No fue posible convertir el objeto a string.");
                }
                break;
            case "boolean": $valor_string .= $valor ? "true" : "false"; break;
            case "double":
            case "integer":  $valor_string .= "{$valor}"; break;
            case "string": $valor_string = $valor; break;

            default:
                throw new Exception("No fue posible convertir el valor proporcionado a string.");
        }

        return $valor_string;
    }

    public static function ObtenerTipoDeDato($valor)
    {
        $tipo_de_dato = NULL;

        switch(gettype($valor))
        {
            case "boolean": $tipo_de_dato = FDW_DATO_BOOL; break;
            case "integer": $tipo_de_dato = FDW_DATO_INT; break;
            case "double": $tipo_de_dato = FDW_DATO_DOUBLE; break;
            case "string": $tipo_de_dato = FDW_DATO_STRING; break;
            case "array": $tipo_de_dato = FDW_DATO_ARRAY; break;
            case "object":
                switch(get_class($valor))
                {
                    case DateTime::class: // Por convensión de FDW, si una fecha tiene como hora 00:00:00.00 se asume que el tipo de dato es FDW_DATO_DATE; de lo contrario es FDW_DATO_DATETIME
                        $tiempo = $valor->format("H:i:s.v");

                        $tipo_de_dato = $tiempo == "00:00:00.000" ? FDW_DATO_DATE : FDW_DATO_DATETIME;
                        break;

                    default:
                        throw new Exception("No fue posible determinar el tipo de dato FDW del objeto.");
                }
                break;

            default:
                throw new Exception("No fue posible determinar el tipo de dato FDW del valor.");
        }

        return $tipo_de_dato;
    }
}
