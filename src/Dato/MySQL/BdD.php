<?php
/**
 * Sistemas Especializados e Innovación Tecnológica, SA de CV
 * Mpsoft.FDW - Framework de Desarrollo Web para PHP
 *
 * v.2.0.0.0 - 2016-10-12
 */
namespace Mpsoft\FDW\Dato\MySQL;

use Mpsoft\FDW\Dato\BdDException;
use Mpsoft\FDW\Core\Parametro;

use mysqli;
use Exception;

/**
 * Clase para realizar operaciones sobre una base de datos MySQL
 */
class BdD extends \Mpsoft\FDW\Dato\BdD
{
    private $conexion = null;

    /**
     * Establece una conexión con el servidor de base de datos.
     * @return boolean Retorna true si se ha establecido una conexión con el servidor de datos, de lo contrario retorna false.
     */
    public function Conectar()
    {
        $this->conexion = @new mysqli($this->parametrosDeConexion->bdd_servidor,
                                        $this->parametrosDeConexion->bdd_usuario,
                                        $this->parametrosDeConexion->bdd_contrasena,
                                        $this->parametrosDeConexion->bdd_bdd);

        if (!$this->conexion->connect_error) // Si se establece una conexión con el servidor de datos
        {
            $this->conectado = true;

            $this->conexion->query("SET NAMES utf8");
        }
        else // Si NO se establece una conexión con el servidor de datos
        {
            $this->conectado = false;
        }

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
    public function EjecutarSELECT($tabla, $campos, $filtros = array(), $ordenamiento = array(), $inicioONumeroDeRegistros = 0, $numeroDeRegistros = 0, $enlaces = array(), $agrupamiento = array())
    {
        $elementos = $this->ConstruirSELECT($tabla, $campos, $filtros, $ordenamiento, $inicioONumeroDeRegistros, $numeroDeRegistros, $enlaces, $agrupamiento);

        $query = "SELECT {$elementos['campos']} FROM {$elementos['tabla']} t {$elementos['enlaces']} {$elementos['filtros']} {$elementos['ordenamiento']} {$elementos['inicioYNumeroDeRegistros']}";

        $resultado = $this->EjecutarSELECT_inseguro($query);

        if($this->conexion->error)
        {
            throw new BDDException($this->conexion->error, get_class($this));
        }

        return $resultado;
    }

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
    public function ConstruirSELECT($tabla, $campos, $filtros = array(), $ordenamiento = array(), $inicioONumeroDeRegistros = 0, $numeroDeRegistros = 0, $enlaces = array(), $agrupamiento = array())
    {
        // Armamos los campos a seleccionar
        $selectoresUtilizados = array();
        $camposASeleccionar = "";
        if(is_array($campos) && count($campos)) // Si hay campos a seleccionar
        {
            foreach($campos as $campo) // Para cada campo
            {
                if($indicePunto = strpos($campo, ".")) // Si ya se especifica la tabla de selección
                {
                    $camposASeleccionar .= "{$campo},";

                    $selector = substr($campo, 0, $indicePunto);
                    $selectoresUtilizados[$selector] = "";
                }
                else // Si no se especifica la tabla de selección
                {
                    if($indice_parentesis = strpos($campo, "(")) // Si el campo especificado es una función  [ p.e.: count(1)]
                    {
                        $camposASeleccionar .= "{$campo},";
                    }
                    else // Si no se especifica la tabla de selección (y no es función)
                    {
                        $camposASeleccionar .= "t.{$campo},";
                    }
                }
            }
            $camposASeleccionar = $this->LimpiarCadena(substr($camposASeleccionar, 0, -1));
        }

        $tabla = $this->LimpiarCadena( $tabla );


        // Agregamos los selectores del filtro a la lista de selectores utilizados
        $filtros_procesados = array();
        if(is_array($filtros) && count($filtros))
        {
            $_filtros_procesados = $this->ProcesarFiltros($filtros);

            $filtros_procesados = $_filtros_procesados["filtros"];
            $selectores_procesados = $_filtros_procesados["selectores"];

            $selectoresUtilizados = array_merge($selectoresUtilizados, $selectores_procesados);
        }

        // Armamos la cláusula WHERE
        $parametrosWhere = $this->ConstruirWHERE($filtros_procesados);
        $WHERE = $parametrosWhere["where"];
        $selectores_where = $parametrosWhere["selectores"];
        $selectores_where_adicionales = array();

        // Es posible que WHERE tenga un campo externo; inyectamos los selectores adicionales
        foreach($selectores_where as $selector=>$nada)
        {
            if( isset($enlaces[$selector]) ) // Si el enlace existe
            {
                $s = $enlaces[$selector];
                $campoInterno = $s["campoInterno"];

                if($indicePunto = strpos($campoInterno, ".")) // Si se especifica selector del campo interno
                {
                    $selector = substr($campoInterno, 0, $indicePunto);
                    $campo = substr($campoInterno, $indicePunto);

                    if($selector !== "t")
                    {
                        $selectores_where_adicionales[$selector] = "";
                    }
                }
            }
            else // Si el enlace no existe
            {
                throw new BdDException("Se especificó el selector inexistente '{$selector}'.");
            }
        }

        $selectoresUtilizados = array_merge($selectores_where_adicionales, $selectoresUtilizados, $selectores_where);


        // Armamos la cláusula ORDER BY
        $ORDER_BY = "";
        if(is_array($ordenamiento) && count($ordenamiento))
        {
            $ORDER_BY = "ORDER BY ";

            foreach($ordenamiento as $campo) // Para cada campo
            {
                $selector = "t.";

                if($indicePunto = strpos($campo, ".")) // Si ya se especifica la tabla de selección
                {
                    $selector = substr($campo, 0, $indicePunto);
                    $campo = substr($campo, $indicePunto);
                    $selectoresUtilizados[$selector] = "";
                }

                $ORDER_BY .= $selector . $campo . ",";
            }
            $ORDER_BY = $this->LimpiarCadena(substr($ORDER_BY, 0, -1));
        }


        // Armamos la cláusula JOIN
        $JOIN = "";
        if(count($selectoresUtilizados)) // Si se utilizó algún selector
        {
            foreach($selectoresUtilizados as $selector=>$vacio) // Para cada enlace
            {
                if($selector == "t") {continue;} // Ignoramos el selector t

                if( isset($enlaces[$selector]) ) // Si el enlace existe
                {
                    $enlace = $enlaces[$selector];

                    // Para soportar el doble join, permitirmos especificar el selector en el campo interno  (así el campo interno puede ser externo)
                    // Si se especifica el selector lo permitimos, de lo contrario agregamos t como selector
                    $campo_interno = strpos($enlace['campoInterno'], ".") ? $enlace['campoInterno'] : "t.{$enlace['campoInterno']}";

                    $JOIN .= "{$enlace['tipo']} JOIN {$enlace['tabla']} {$selector} ON {$campo_interno}={$selector}.{$enlace['campoExterno']} ";
                }
                else // Si el enlace no existe
                {
                    throw new BdDException("No se especificó el selector '{$selector}'.");
                }
            }
        }


        // Armamos la cláusula LIMIT
        $LIMIT = "";
        if($inicioONumeroDeRegistros > 0 || $numeroDeRegistros>0) // Si se especifica el registro inicial o número de registros
        {
            if($numeroDeRegistros > 0) // Si se especifica el número de registros
            {
                $LIMIT = "LIMIT {$inicioONumeroDeRegistros},{$numeroDeRegistros}";
            }
            else // Si no se especifica el número de registros
            {
                $LIMIT = "LIMIT {$inicioONumeroDeRegistros}";
            }
        }

        return array("campos"=>$camposASeleccionar, "tabla"=>$tabla, "enlaces"=>$JOIN, "filtros"=>$WHERE, "ordenamiento"=>$ORDER_BY, "agrupamiento"=>"", "inicioYNumeroDeRegistros"=>$LIMIT);
    }

    private function ProcesarFiltros($grupos_de_filtros):array
    {
        $filtros_procesados = array();
        $selectores_procesados = array();

        foreach($grupos_de_filtros as $grupo_nombre=>$grupo_de_filtros) // Para cada grupo de filtros
        {
            if(isset($grupo_de_filtros["filtros"])) // Si es un grupo de filtros
            {
                $subfiltros_procesados = $this->ProcesarFiltros($grupo_de_filtros["filtros"]);

                $grupo_nombre = $this->LimpiarCadena($grupo_nombre);
                $grupo_logica = isset($grupo_de_filtros["tipo"]) ? $grupo_de_filtros["tipo"] : NULL;

                $filtros_procesados[$grupo_nombre] = array("filtros" => $subfiltros_procesados["filtros"], "tipo"=>$grupo_logica);
                $selectores_procesados = array_merge($selectores_procesados, $subfiltros_procesados["selectores"]);
            }
            else // Si es un filtro de campo
            {
                $selector = "t.";

                if($indicePunto = strpos($grupo_nombre, ".")) // Si ya se especifica la tabla de selección
                {
                    $selector = substr($grupo_nombre, 0, $indicePunto);
                    $grupo_nombre = substr($grupo_nombre, $indicePunto);
                    $selectores_procesados[$selector] = "";
                }

                $grupo_nombre = $this->LimpiarCadena("{$selector}{$grupo_nombre}");

                $filtros_procesados[$grupo_nombre] = $grupo_de_filtros;
            }
        }

        return array("filtros"=>$filtros_procesados, "selectores"=>$selectores_procesados);
    }

    /**
     * Ejecuta una consulta SELECT sobre la base de datos.
     * @param string $query Consulta SELECT que será ejecutada sobre la base de datos.
     * @return mixed Retorna el resultado de la consulta ejecutada o NULL si ocurre un error.
     */
    public function EjecutarSELECT_inseguro($query)
    {
        global $CFG;

        if (!$this->conectado) // Si la conexión no se ha establecido
        {
            $this->Conectar();
        }

        $resultado = null;

        if ($this->conectado) // Si hay conexión con el servidor
        {
            if ($mysql_result = $this->conexion->query($query)) // Si se obtiene un resultado con la consulta
            {
                $resultado = new ResultadoDeConsulta($mysql_result);
            }
        }
        else // Si no hay conexión con el servidor
        {
            throw new BdDException("No fue posible establecer una conexión con el servidor de datos.", get_class($this));
        }

        return $resultado;
    }

    /**
     * Construye la cláusula WHERE a patir del grupo de filtros proporcionados
     * @param mixed $grupos_de_filtros Arreglo asociativo de arreglo de arreglos con la información de los filtro a aplicar. array( "Campo1" => array( array("tipo"=> "AND|OR" (opcional, se asume AND), "operador"=> ** Ver operadores **, "operando"=>Valor), array("operador"=> ** Ver operadores **, "operando"=>Valor) ) )
     * @throws Exception
     * @return array
     */
    public function ConstruirWHERE($grupos_de_filtros):array
    {
        $WHERE = "";
        $selectoresUtilizados = array();

        if(count($grupos_de_filtros)) // Si se han especificado filtros
        {
            $_where = $this->_ConstruirWHERE($grupos_de_filtros);

            $WHERE = "WHERE " . $_where["where"];
            $selectoresUtilizados = $_where["selectores"];
        }

        return array("where"=>$WHERE, "selectores"=>$selectoresUtilizados);
    }

    private function _ConstruirWHERE($grupos_de_filtros):array
    {
        $WHERE = "";
        $selectoresUtilizados = array();

        $conteoDeFiltros = 0;

        foreach($grupos_de_filtros as $grupo_nombre=>$filtros_del_grupo) // Para cada grupo de filtros
        {
            if(isset($filtros_del_grupo["filtros"])) // Si es un grupo de filtros
            {
                $_where_construido = $this->_ConstruirWHERE($filtros_del_grupo["filtros"]);
                $_where = $_where_construido["where"];
                $_selectores = $_where_construido["selectores"];
                $_conteo = $_where_construido["conteo"];

                // Determinamos el tipo de lógica
                $tipo = "";
                if($conteoDeFiltros>0) // Si ya hay otros filtros
                {
                    $tipo = isset($filtros_del_grupo["tipo"]) ? $this->TraducirLogica($filtros_del_grupo["tipo"]) : $this->TraducirLogica(FDW_DATO_BDD_LOGICA_Y);
                }

                $parentesis_que_abre = $_conteo > 1 ? "(" : "";
                $parentesis_que_cierra = $_conteo > 1 ? ")" : "";

                $WHERE .= "{$tipo} {$parentesis_que_abre}{$_where}{$parentesis_que_cierra}";

                $conteoDeFiltros += $_conteo;
            }
            else // Si es un campo
            {
                $selector = "t.";

                if($indicePunto = strpos($grupo_nombre, ".")) // Si ya se especifica la tabla de selección
                {
                    $selector = substr($grupo_nombre, 0, $indicePunto);
                    $grupo_nombre = substr($grupo_nombre, $indicePunto);

                    if($selector !== "t")
                    {
                        $selectoresUtilizados[$selector] = "";
                    }
                }

                $grupo_nombre = $this->LimpiarCadena( $selector . $grupo_nombre );

                foreach($filtros_del_grupo as $filtro) // Para cada filtro del campo
                {
                    // Determinamos el tipo de lógica
                    $tipo = "";
                    if($conteoDeFiltros>0) // Si ya hay otros filtros
                    {
                        $tipo = isset($filtro["tipo"]) ? $this->TraducirLogica($filtro["tipo"]) : $this->TraducirLogica(FDW_DATO_BDD_LOGICA_Y);
                    }

                    $operador = $this->TraducirOperador($filtro["operador"], $filtro["operando"]);

                    if($filtro["operando"] !== NULL) // Si se especifica el operando
                    {
                        if($filtro["operador"] == FDW_DATO_BDD_OPERADOR_IN || $filtro["operador"] == FDW_DATO_BDD_OPERADOR_NOT_IN) // Si es operador IN o NOT IN
                        {
                            if(is_array($filtro["operando"]) && count($filtro["operando"]) > 0)  // Si se proporciona un arreglo no vacío
                            {
                                // Colocamos comillas a los campos que no son enteros
                                foreach($filtro["operando"] as $indice=>$o)
                                {
                                    if( is_string($o) )
                                    {
                                        $filtro["operando"][$indice] = "'{$o}'";
                                    }
                                }

                                $in = implode(",", $filtro["operando"]);

                                $operando = "({$in})";
                            }
                            else // Si no se proporciona un arreglo o se proporciona uno vacío
                            {
                                throw new Exception("El operador FDW_DATO_BDD_OPERADOR_IN o FDW_DATO_BDD_OPERADOR_NOT_IN espera un array no vacío como operando.");
                            }
                        }
                        else // Si no es operador IN
                        {
                            if(is_bool($filtro["operando"])) // Si el operando es booleano
                            {
                                $operando = $filtro["operando"] ? 'true' : 'false';
                            }
                            else // Si el operador no es booleano
                            {
                                $operando = NULL;

                                if(is_a($filtro["operando"], 'DateTime')) // Si el operador es un DateTime
                                {
                                    $operando_str = Parametro::LimpiarValor($filtro["operando"], FDW_DATO_STRING);

                                    $operando = "'{$operando_str}'";
                                }
                                else // Si el operador no es un DateTime
                                {
                                    $operando = is_string($filtro["operando"]) ? "'" . $this->LimpiarCadena( $filtro["operando"] ) . "'" : $this->LimpiarCadena( $filtro["operando"] );
                                }
                            }
                        }
                    }
                    else // Si no se especifica el operando
                    {
                        $operando = "NULL";
                    }

                    $WHERE .= "{$tipo} {$grupo_nombre} {$operador} {$operando} ";

                    $conteoDeFiltros++;
                }
            }
        }

        return array("where"=>$WHERE, "selectores"=>$selectoresUtilizados, "conteo"=>$conteoDeFiltros);
    }


    /**
     * Ejecuta una consulta INSERT sobre la base de datos.
     * @param string $tabla Nombre de la tabla sobre la cual se insertarán los datos.
     * @param array $campos Arreglo con el listado de campos, correspondientes al arreglo $valores. array("Campo1", "Campo2")
     * @param array $valores Valores de los campos que se agregarán a la base de datos.
     * @return int Regresa el ID del registro creado.
     */
    public function EjecutarINSERT($tabla, $campos, $valores)
    {
        // Verificamos que los campos y los valores sean los mismos
        $conteo_campos = count($campos);
        $conteo_valores = count($valores);
        if($conteo_campos > 0) // Si hay campos
        {
            if($conteo_campos != $conteo_valores) // Si los campos y los valores no son válidos
            {
                throw new BdDException("El listado de campos y valores no coinciden.", get_class($this));
            }
        }
        else // Si no hay campos
        {
            throw new BdDException("No se proporcionó el listado de campos que se insertarán.", get_class($this));
        }

        $tabla = $this->LimpiarCadena( $tabla );

        $camposAInsertar = $this->LimpiarCadena( implode(",", $campos) );
        $valoresAInsertar = "";

        foreach($valores as $valor) // Para cada valor proporcionado
        {
            // Este código está parcialmente replicado en EjecutarUPDATE
            $valor_string = $valor === NULL ? "null" : BdD::ConvertirAString($valor);
            $valor_string = $this->LimpiarCadena($valor_string);

            switch(gettype($valor))
            {
                case "boolean":
                case "double":
                case "integer":
                case "NULL": $valoresAInsertar .= $valor_string; break;

                case "object":
                case "string": $valoresAInsertar .= "'{$valor_string}'"; break;
                default:
                    throw new Exception("No fue posible convertir el valor proporcionado a string.");
            }

            $valoresAInsertar .= ",";
        }
        $valoresAInsertar = substr($valoresAInsertar, 0, -1);

        $query = "INSERT INTO {$tabla}({$camposAInsertar}) VALUES({$valoresAInsertar})";


        $resultado = $this->EjecutarINSERT_inseguro($query);

        if($this->conexion->error)
        {
            throw new BdDException($this->conexion->error, get_class($this));
        }

        return $resultado;
    }

    /**
     * Ejecuta una consulta INSERT sobre la base de datos.
     * @param string $query Consulta INSERT que será ejecutada sobre la base de datos.
     * @return int Regresa el ID del registro creado en caso de éxito, de lo contrario regresa null.
     */
    public function EjecutarINSERT_inseguro($query)
    {
        global $CFG;

        if (!$this->conectado) // Si la conexión no se ha establecido
        {
            $this->Conectar();
        }

        $id = NULL;

        if ($this->conectado) // Si hay conexión con el servidor
        {
            $this->conexion->query($query);

            $id = $this->conexion->insert_id;
        }
        else // Si no hay conexión con el servidor
        {
            throw new BdDException("No fue posible establecer una conexión con el servidor de datos.", get_class($this));
        }

        return $id;
    }

    /**
     * Ejecuta una consulta UPDATE sobre la base de datos.
     * @param string $tabla Nombre de la tabla sobre la cual se modificarán los datos.
     * @param array $campos Arreglo con el listado de campos, correspondientes al arreglo $valores. array("Campo1", "Campo2")
     * @param array $valores Valores de los campos que se modificarán en la base de datos.
     * @param array $filtros Arreglo asociativo de arreglo de arreglos con la información de los filtro a aplicar. array( "Campo1" => array( array("tipo"=> ** Ver lógica ** (opcional, se asume FDW_DATO_BDD_LOGICA_Y), "operador"=> ** Ver operadores **, "operando"=>Valor), array("operador"=> ** Ver operadores **, "operando"=>Valor) ) )
     * @return boolean Regresa true en caso de éxito.
     */
    public function EjecutarUPDATE($tabla, $campos, $valores, $filtros = array())
    {
        // Verificamos que los campos y los valores sean los mismos
        $conteo_campos = count($campos);
        $conteo_valores = count($valores);
        if($conteo_campos > 0) // Si hay campos
        {
            if($conteo_campos != $conteo_valores) // Si los campos y los valores no son válidos
            {
                throw new BdDException("El listado de campos y valores no coinciden.", get_class($this));
            }
        }
        else // Si no hay campos
        {
            throw new BdDException("No se proporcionó el listado de campos que se actualizarán.", get_class($this));
        }

        $tabla = $this->LimpiarCadena( $tabla );

        $query = "UPDATE {$tabla} t SET ";
        foreach($campos as $indice=>$valor) // Para cada campo
        {
            $campo = $this->LimpiarCadena($valor);
            $valor = $valores[$indice];


            $query .= "{$campo}=";

            // Este código está parcialmente replicado en EjecutarINSERT
            $valor_string = $valor === NULL ? "null" : BdD::ConvertirAString($valor);
            $valor_string = $this->LimpiarCadena($valor_string);

            switch(gettype($valor))
            {
                case "boolean":
                case "double":
                case "integer":
                case "NULL": $query .= $valor_string; break;

                case "object":
                case "string": $query .= "'{$valor_string}'"; break;
                default:
                    throw new Exception("No fue posible convertir el valor proporcionado a string.");
            }

            $query .= ",";
        }
        $query = substr($query, 0, -1); // Quitamos la última coma

        // Armamos la cláusula WHERE
        $parametrosWhere = $this->ConstruirWHERE($filtros);
        $WHERE = $parametrosWhere["where"];

        $resultado = $this->EjecutarUPDATE_inseguro($query . " {$WHERE}");

        if($this->conexion->error)
        {
            throw new BdDException($this->conexion->error, get_class($this));
        }

        return $resultado;
    }

    /**
     * Ejecuta una consulta UPDATE sobre la base de datos.
     * @param string $query Consulta UPDATE que será ejecutada sobre la base de datos.
     * @return boolean Regresa true en caso de éxito, en caso contrario retorna false.
     */
    public function EjecutarUPDATE_inseguro($query)
    {
        global $CFG;

        if (!$this->conectado) // Si la conexión no se ha establecido
        {
            $this->Conectar();
        }

        $estado = FALSE;

        if ($this->conectado) // Si hay conexión con el servidor
        {
            if ($this->conexion->query($query))
            {
                $estado = TRUE;
            }
        }
        else // Si no hay conexión con el servidor
        {
            throw new BdDException("No fue posible establecer una conexión con el servidor de datos.", get_class($this));
        }

        return $estado;
    }

    /**
     * Ejecuta una consulta DELETE sobre la base de datos.
     * @param string $tabla Nombre de la tabla sobre la cual se eliminarán los datos.
     * @param array $filtros Arreglo asociativo de arreglo de arreglos con la información de los filtro a aplicar. array( "Campo1" => array( array("tipo"=> ** Ver lógica ** (opcional, se asume FDW_DATO_BDD_LOGICA_Y), "operador"=> ** Ver operadores **, "operando"=>Valor), array("operador"=> ** Ver operadores **, "operando"=>Valor) ) )
     */
    public function EjecutarDELETE($tabla, $filtros = array())
    {
        $tabla = $this->LimpiarCadena( $tabla );

        $query = "DELETE t FROM {$tabla} t ";

        // Armamos la cláusula WHERE
        $parametrosWhere = $this->ConstruirWHERE($filtros);
        $WHERE = $parametrosWhere["where"];

        $resultado = $this->EjecutarDELETE_inseguro($query . " {$WHERE}");

        if($this->conexion->error)
        {
            throw new BdDException($this->conexion->error, get_class($this));
        }

        return $resultado;
    }

    /**
     * Ejecuta una consulta UPDATE sobre la base de datos.
     * @param string $query Consulta UPDATE que será ejecutada sobre la base de datos.
     * @return boolean Regresa true en caso de éxito, en caso contrario retorna false.
     */
    public function EjecutarDELETE_inseguro($query)
    {
        global $CFG;

        if (!$this->conectado) // Si la conexión no se ha establecido
        {
            $this->Conectar();
        }

        $estado = FALSE;

        if ($this->conectado) // Si hay conexión con el servidor
        {
            if ($this->conexion->query($query))
            {
                $estado = TRUE;
            }
        }
        else // Si no hay conexión con el servidor
        {
            throw new BdDException("No fue posible establecer una conexión con el servidor de datos.", get_class($this));
        }

        return $estado;
    }


    /**
     * Limpia la entrada para usarla de manera segura consultas en MySQL
     * @param string $str Cadena a limpiar
     * @return string Cadena limpia
     */
    private function LimpiarCadena($str)
    {
        $search = array("\\",  "\x00", "\n",  "\r",  "'",  '"', "\x1a");
        $replace = array("\\\\","\\0","\\n", "\\r", "\'", '\"', "\\Z");

        return str_replace($search, $replace, $str);
    }

    private function TraducirLogica($logica)
    {
        $traduccion = "";

        switch ($logica)
        {
            case FDW_DATO_BDD_LOGICA_Y: $traduccion = "AND"; break;
            case FDW_DATO_BDD_LOGICA_O: $traduccion = "OR"; break;
            default: throw new BdDException("Lógica '{$logica}' no soportado.", get_class($this));
        }

        return $traduccion;
    }

    private function TraducirOperador($operador, $operando)
    {
        $traduccion = "";

        switch ($operador)
        {
            case FDW_DATO_BDD_OPERADOR_IGUAL: $operando !== NULL ? $traduccion = "=" : $traduccion = "IS"; break;
            case FDW_DATO_BDD_OPERADOR_MAYORQUE: $traduccion = ">"; break;
            case FDW_DATO_BDD_OPERADOR_MAYOROIGUALQUE: $traduccion = ">="; break;
            case FDW_DATO_BDD_OPERADOR_MENORQUE: $traduccion = "<"; break;
            case FDW_DATO_BDD_OPERADOR_MENOROIGUALQUE: $traduccion = "<="; break;
            case FDW_DATO_BDD_OPERADOR_DIFERENTE: $operando !== NULL ? $traduccion = "<>" : $traduccion = "IS NOT"; break;
            case FDW_DATO_BDD_OPERADOR_LIKE: $traduccion = "LIKE"; break;
            case FDW_DATO_BDD_OPERADOR_IN: $traduccion = "IN"; break;
            case FDW_DATO_BDD_OPERADOR_IGUAL_BINARIO: $traduccion = "= BINARY"; break;
            case FDW_DATO_BDD_OPERADOR_NOT_IN: $traduccion = "NOT IN"; break;


            default: throw new BdDException("Operador '{$operador}' no soportado.", get_class($this));
        }

        return $traduccion;
    }

    /**
     * Inicia una transacción en la base de datos.
     */
    public function IniciarTransaccion()
    {
        if (!$this->conectado) // Si la conexión no se ha establecido
        {
            $this->Conectar();
        }

        $this->conexion->begin_transaction();

        $this->conexion->autocommit(FALSE);
    }

    /**
     * Confirma los cambios realizados a la base de datos.
     */
    public function RealizarCommit()
    {
        $this->conexion->commit();
    }

    /**
     * Cancela los cambios realizados a la base de datos.
     */
    public function RealizarRollback()
    {
        $this->conexion->rollback();
    }
}
