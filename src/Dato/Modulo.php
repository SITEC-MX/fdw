<?php
/**
 * Sistemas Especializados e Innovación Tecnológica, SA de CV
 * Mpsoft.FDW - Framework de Desarrollo Web para PHP
 *
 * v.2.0.0.0 - 2016-11-07
 */
namespace Mpsoft\FDW\Dato;

abstract class Modulo
{
    protected $clase_actual;
    private $base_de_datos;

    private $campos;
    private $filtros;
    private $ordenamiento;
    private $inicioONumeroDeRegistros;
    private $numeroDeRegistros;

    private $enlaces;

    private $resultado = NULL;

    /**
     * Arreglo con la información de los campos de la tabla. Este campo es llenado por el método _ObtenerInformacionDeCampos()
     * @var array Arreglo con la información de los campos de la tabla (nombre del campo, campo requerido, campo de solo lectura, etc.)
     */
    private $informacionDeCampos;

    /**
     * Construye un Módulo.
     * @param array $campos Arreglo con el listado de campos del Módulo que se seleccionarán. array("Campo1", "Campo2")
     * @param array $filtros Arreglo asociativo de arreglo de arreglos con la información de los filtro a aplicar. array( "Campo1" => array( array("tipo"=> ** Ver lógica ** (opcional, se asume FDW_DATO_BDD_LOGICA_Y), "operador"=> ** Ver operadores **, "operando"=>Valor), array("operador"=> ** Ver operadores **, "operando"=>Valor) ) )
     * @param array $ordenamiento Arreglo con los campos que se utilizarán para ordenar la consulta. array("Campo1", "Campo2 DESC")
     * @param int $inicioONumeroDeRegistros Si se especifica $numeroDeRegistros es el registro inicial que se seleccionará, de lo contrario es el total de registros a seleccionar.
     * @param int $numeroDeRegistros Total de registros a seleccionar.
     */
    public function __construct(array $campos = NULL, array $filtros = NULL, array $ordenamiento = NULL, int $inicioONumeroDeRegistros = 0, int $numeroDeRegistros = 0)
    {
        $this->clase_actual = get_class($this);

        if (!$this->PedirAutorizacion(FDW_DATO_PERMISO_OBTENER))
        {
            throw new ModuloException("No cuenta con permiso para obtener los datos del Módulo '{$this->clase_actual}'.");
        }



        $camposSolicitados = $campos ? $campos : $this->_ObtenerCamposPredeterminados();

        $this->inicioONumeroDeRegistros = $inicioONumeroDeRegistros;
        $this->numeroDeRegistros = $numeroDeRegistros;

        $this->informacionDeCampos = $this->ObtenerInformacionDeCampos();
        $this->enlaces = $this->_ObtenerJoins();

        /* Verificamos que los campos seleccionados sean válidos */
        $this->campos = array();

        foreach($camposSolicitados as $campo) // Para cada campo solicitado
        {
            if( !isset($this->informacionDeCampos[$campo]) ) // Si el campo no existe
            {
                throw new ModuloException("El campo '{$campo}' no pertenece al Módulo.", $this->clase_actual);
            }

            if(isset($this->informacionDeCampos[$campo]["identificadorJoin"])) // Si el campo pertenece a otra tabla
            {
                $tabla = $this->informacionDeCampos[$campo]["identificadorJoin"];
                $campo = $this->informacionDeCampos[$campo]["campoExterno"] . " {$campo}";
            }
            else // Si el campo pertenece a la tabla de trabajo
            {
                $tabla = "t";
            }

            $this->campos[] = "{$tabla}.{$campo}";
        }



        /* Verificamos que los campos de ordenamiento sean válidos */
        if($ordenamiento)
        {
            $this->ordenamiento = array();

            foreach($ordenamiento as $campo_orden) // Para cada campo de ordenamiento
            {
                $e_c_o = explode(" ", $campo_orden);

                if( count($e_c_o) > 2) // Si se envían más campos de los esperados para el ordenamiento
                {
                    throw new ModuloException("El ordenamiento proporcionado no es válido.", $this->clase_actual);
                }

                $campo = $e_c_o[0];
                $orden = isset($e_c_o[1]) ? $e_c_o[1] : "";

                if( !isset($this->informacionDeCampos[$campo]) ) // Si el campo no existe
                {
                    throw new ModuloException("El campo '{$campo}' no pertenece al Módulo.", $this->clase_actual);
                }

                if(isset($this->informacionDeCampos[$campo]["identificadorJoin"])) // Si el campo pertenece a otra tabla
                {
                    $tabla = $this->informacionDeCampos[$campo]["identificadorJoin"];
                    $campo = $this->informacionDeCampos[$campo]["campoExterno"];
                }
                else // Si el campo pertenece a la tabla de trabajo
                {
                    $tabla = "t";
                }

                $this->ordenamiento[] = "{$tabla}.{$campo} {$orden}";
            }
        }


        $this->filtros = isset($filtros) ? $this->ConstruirFiltros($filtros) : array();

        $this->base_de_datos = $this->_InicializarBaseDeDatos();
    }

    private function ConstruirFiltros(array $grupos_de_filtros):array
    {
        $filtros_verificados = array();

        foreach($grupos_de_filtros as $grupo_nombre=>$filtros_del_grupo) // Para cada filtro enviado
        {
            if(isset($filtros_del_grupo["filtros"])) // Si es un grupo de filtros
            {
                $tipo_del_filtro = isset($filtros_del_grupo["tipo"]) ? $filtros_del_grupo["tipo"] : NULL;

                $filtros_verificados[$grupo_nombre] = array
                (
                    "filtros"=>$this->ConstruirFiltros($filtros_del_grupo["filtros"]), 
                    "tipo"=>$tipo_del_filtro
                );
            }
            else // Si es un filtro
            {
                if( isset($this->informacionDeCampos[$grupo_nombre]) ) // Si el campo existe
                {
                    if(isset($this->informacionDeCampos[$grupo_nombre]["identificadorJoin"])) // Si el campo pertenece a otra tabla
                    {
                        $identificador = $this->informacionDeCampos[$grupo_nombre]["identificadorJoin"];
                        $grupo_nombre = $this->informacionDeCampos[$grupo_nombre]["campoExterno"];
                    }
                    else // Si el campo pertenece a la tabla de trabajo
                    {
                        $identificador = "t";
                    }

                    $filtros_verificados["{$identificador}.{$grupo_nombre}"] = $filtros_del_grupo;
                }
                else // Si el campo no existe
                {
                    throw new ModuloException("El campo '{$grupo_nombre}' utilizado en el filtro no pertenece al Módulo.", $this->clase_actual);
                }
            }
        }

        return $filtros_verificados;
    }

    private function ObtenerDatos():void
    {
        // Permisos validados en constructor

        $this->resultado = $this->base_de_datos->EjecutarSELECT($this->ObtenerNombreTabla(), $this->campos, $this->filtros, $this->ordenamiento, $this->inicioONumeroDeRegistros, $this->numeroDeRegistros, $this->enlaces);
    }

    public function ObtenerSiguienteRegistro():?array
    {
        if (!$this->resultado) // Si los datos no se han obtenido
        {
            $this->ObtenerDatos();
        }

        $registro = $this->resultado->ObtenerSiguienteResultado();

        // Componemos los tipos
        if($registro) // Si se obtiene un resultado
        {
            foreach($registro as $campo_nombre=>$campo_valor) // Para cada campo
            {
                $registro[$campo_nombre] = BdD::ConvertirATipoDeDato($registro[$campo_nombre], $this->informacionDeCampos[$campo_nombre]["tipoDeDato"]);
            }
        }

        return $registro;
    }

    public function ObtenerConteo():int
    {
        $conteo = -1;

        $conteo_resultado = $this->base_de_datos->EjecutarSELECT($this->ObtenerNombreTabla(), array("count(1) conteo"), $this->filtros, NULL, NULL, NULL, $this->enlaces);

        if($conteo_resultado) // Si hay resultado
        {
            $resultado = $conteo_resultado->ObtenerSiguienteResultado();

            $conteo = BdD::ConvertirATipoDeDato($resultado["conteo"], FDW_DATO_INT);
        }

        return $conteo;
    }


    /**
     * Verifica si se tiene permiso para realizar la acción sobre el Elemento
     * @param int $accion Acción a verificar
     * @return boolean
     */
    protected abstract function PedirAutorizacion(int $accion):bool;

    /**
     * Inicializa el gestor de base de datos utilizado por el Elemento
     * @return \Mpsoft\FDW\Dato\BdD
     */
    protected function _InicializarBaseDeDatos():BdD
    {
        return $this->clase_actual::InicializarBaseDeDatos();
    }

    /**
     * Obtiene el nombre de la tabla en la base de datos utilizada por el Elemento
     * @return string
     */
    protected function _ObtenerNombreTabla():string
    {
        return $this->clase_actual::ObtenerNombreTabla();
    }

    /**
     * Retorna un arreglo asociativo cuyo índice es el "nombreSQL" del campo (nombre del campo en la tabla del Elemento) y apunta a un arreglo asociativos con la información del campo del Elemento
     * @return array Arreglo asociativo de arreglos asociativos con los siguientes índices:
     * "requerido"               (Opcional) true si el campo es requerido,
     * "soloDeLectura"           (Opcional) true si campo de es de solo lectura,
     * "nombre"			        (Opcional) nombre del campo para el usuario. Si no se especifica será igual al nombreSQL
     * "tipoDeDato"              (Opcional) tipo de dato del campo, se asume FDW_DATO_STRING si no se especifica.
     * "tamanoMaximo"		    (Opcional) si no es especifica se asume 0 (ilimitado) tamaño máximo del campo (aplica sólo para el tipo de datos FDW_DATO_STRING)
     *
     * "ignorarAlAgregar"	    (Opcional) si se especifica en true el campo no se incluirá al agregar el Elemento.
     * "ignorarAlModificar"      (Opcional) si se especifica en true el campo no se incluirá al modificar el Elemento.
     * "ignorarAlObtenerValores" (Opcional) si se especifica en true el campo no se incluirá al obtener los valores del Elemento.
     *
     *  Ejemplo "id" => array("requerido" => true, "soloDeLectura" => true, "nombre" => "ID", "tipoDeDato" => FDW_DATO_INT)
     *
     * NOTA: El Elemento debe contener el campo "id"
     */
    protected function _ObtenerInformacionDeCampos():array
    {
        return $this->clase_actual::ObtenerInformacionDeCampos();
    }

    /**
     * Obtiene un arreglo de strings con los campos predeterminados del Elemento. Serán utilizado sólo en caso que no se especifiquen.
     * @return array
     */
    protected function _ObtenerCamposPredeterminados():array
    {
        return $this->clase_actual::ObtenerCamposPredeterminados();
    }

    /*
     * Obtiene un array asociativo de arrays asociativos con la información de los joins realizados en el Módulo. array( identificador => array( "tipo", "tabla", "campoInterno", "campoExterno" )  )
     * @return array Retorna un array de arrays asociativos con la información de los joins realizados en el Módulo. Retorna un array vacío si no hay joins. array( identificador => array( "tipo", "tabla", "campoInterno", "campoExterno" )  )
     */
    protected function _ObtenerJoins():?array
    {
        return $this->clase_actual::ObtenerJoins();
    }



    /**
     * Inicializa el gestor de base de datos utilizado por el Elemento
     * @return \Mpsoft\FDW\Dato\BdD
     */
    public abstract static function InicializarBaseDeDatos():BdD;

    /**
     * Obtiene el nombre de la tabla en la base de datos utilizada por el Elemento
     * @return string
     */
    public abstract static function ObtenerNombreTabla():string;

    /**
     * Retorna un arreglo asociativo cuyo índice es el "nombreSQL" del campo (nombre del campo en la tabla del Elemento) y apunta a un arreglo asociativos con la información del campo del Elemento
     * @return array Arreglo asociativo de arreglos asociativos con los siguientes índices:
     * "requerido"               (Opcional) true si el campo es requerido,
     * "soloDeLectura"           (Opcional) true si campo de es de solo lectura,
     * "nombre"			        (Opcional) nombre del campo para el usuario. Si no se especifica será igual al nombreSQL
     * "tipoDeDato"              (Opcional) tipo de dato del campo, se asume FDW_DATO_STRING si no se especifica.
     * "tamanoMaximo"		    (Opcional) si no es especifica se asume 0 (ilimitado) tamaño máximo del campo (aplica sólo para el tipo de datos FDW_DATO_STRING)
     *
     * "ignorarAlAgregar"	    (Opcional) si se especifica en true el campo no se incluirá al agregar el Elemento.
     * "ignorarAlModificar"      (Opcional) si se especifica en true el campo no se incluirá al modificar el Elemento.
     * "ignorarAlObtenerValores" (Opcional) si se especifica en true el campo no se incluirá al obtener los valores del Elemento.
     *
     *  Ejemplo "id" => array("requerido" => true, "soloDeLectura" => true, "nombre" => "ID", "tipoDeDato" => FDW_DATO_INT)
     *
     * NOTA: El Elemento debe contener el campo "id"
     */
    public abstract static function ObtenerInformacionDeCampos():array;

    /**
     * Obtiene un arreglo de strings con los campos predeterminados del Elemento. Serán utilizado sólo en caso que no se especifiquen.
     * @return array
     */
    public abstract static function ObtenerCamposPredeterminados():array;

    /*
     * Obtiene un array asociativo de arrays asociativos con la información de los joins realizados en el Módulo. array( identificador => array( "tipo", "tabla", "campoInterno", "campoExterno" )  )
     * @return array Retorna un array de arrays asociativos con la información de los joins realizados en el Módulo. Retorna un array vacío si no hay joins. array( identificador => array( "tipo", "tabla", "campoInterno", "campoExterno" )  )
     */
    public abstract static function ObtenerJoins():?array;



    /**
     * Obtiene la consulta de selección que se utiliza en el Modulo al obtener datos.
     * @param mixed $modulo
     */
    public static function ObtenerConsultaDeSeleccion(Modulo $modulo):array
    {
        $base_de_datos = $modulo->_InicializarBaseDeDatos();

        return $base_de_datos->ConstruirSELECT($modulo->_ObtenerNombreTabla(), $modulo->campos, $modulo->filtros, $modulo->ordenamiento, $modulo->inicioONumeroDeRegistros, $modulo->numeroDeRegistros, $modulo->enlaces);
    }
}
