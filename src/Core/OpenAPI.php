<?php
/**
 * Sistemas Especializados e Innovación Tecnológica, SA de CV
 * Mpsoft.FDW - Framework de Desarrollo Web para PHP
 *
 * v.2.0.0.0 - 2020-06-28
 */
namespace Mpsoft\FDW\Core;

use Mpsoft\FDW\Dato\BdD;

/**
 * Compatibilidad de FDW con la especificación OpenAPI
 */
abstract class OpenAPI
{
    public static function ObtenerLlamadaSolicitada(array $definicion_de_llamadas)
    {
        /* Obtenemos la llamada solicitada */
        $LLAMADASOLICITADA = substr($_SERVER["REQUEST_URI"], 1); // Sin / al inicio
        $INDICE_PARAMETRO = strpos($LLAMADASOLICITADA, "?"); // Quitamos los parámetros recibidos por $_GET
        if($INDICE_PARAMETRO !== FALSE) // Si hay parámetros
        {
            $LLAMADASOLICITADA = substr($LLAMADASOLICITADA, 0, $INDICE_PARAMETRO);
        }

        $llamada_solicitada = NULL;

        // Determinamos qué llamada de la definición son posibles por la URL
        $pm_matches = NULL;
        foreach($definicion_de_llamadas as $url=>$definicion) // Para cada llamada disponible
        {
            if(preg_match($url, $LLAMADASOLICITADA, $pm_matches) == 1) // Si encontramos la llamada solicitada
            {
                $llamada_solicitada = $definicion;
                break;
            }
        }

        $llamada = NULL;
        if($llamada_solicitada) // Si se obtiene la defición de la llamada solicitada
        {
            // Procesamos las variables de la URL
            $variable = array(); // Las variables proporcionadas en la url
            foreach($llamada_solicitada["variables"] as $variable_nombre=>$variable_contenedor) // Para cada variable disponible en la definición
            {
                if( isset($pm_matches[$variable_nombre]) ) // Si se define la variable
                {
                    $variable[$variable_nombre] = Parametro::LimpiarValor($pm_matches[$variable_nombre], $variable_contenedor["tipo"]);
                }
            }
            $llamada = array("variable"=>$variable);

            $llamada["script_php_ruta"] = $llamada_solicitada["script_php_ruta"];

            $REQUEST_METHOD = $_SERVER["REQUEST_METHOD"];
            if( isset($llamada_solicitada["metodos"][$REQUEST_METHOD]) ) // Si el método solicitado está definido
            {
                $llamada["metodo"] = $REQUEST_METHOD;

                $metodo_solicitado = $llamada_solicitada["metodos"][$REQUEST_METHOD];
                $llamada["autenticar"] = $metodo_solicitado["autenticar"];


                // Procesamos los valores porporcionados en $_GET
                $get = array(); // La información definida en $_GET
                $get_no_proporcionado = NULL;
                if(isset($metodo_solicitado["get"])) // Si el método tiene campos en GET
                {
                    foreach($metodo_solicitado["get"] as $get_nombre=>$get_contenedor) // Para cada campo definido
                    {
                        if( isset($_GET[$get_nombre]) ) // Si el campo se proporciona en GET
                        {
                            $get[$get_nombre] = Parametro::LimpiarValor($_GET[$get_nombre], $get_contenedor["tipo"]);
                        }
                        else // Si el campo no se proporciona en GET
                        {
                            if($get_contenedor["requerido"]) // Si el campo es requerido
                            {
                                $get_no_proporcionado = $get_nombre;
                                break; // Terminamos, no es necesario procesar los demás campos.
                            }
                            else // Si el campo no es requerido
                            {
                                if(isset($get_contenedor["default"])) // Si se especifica el valor por defecto
                                {
                                    $get[$get_nombre] = Parametro::LimpiarValor($get_contenedor["default"], $get_contenedor["tipo"]);
                                }
                            }
                        }
                    }
                }
                $llamada["get"] = $get;
                $llamada["get_no_proporcionado"] = $get_no_proporcionado;

                // Procesamos los valores porporcionados en $_POST (si es que aplica)
                $body = NULL;
                $body_no_proporcionado = NULL;
                if($REQUEST_METHOD == "POST" || $REQUEST_METHOD == "PATCH") // Si es un método que puede tener cuerpo
                {
                    $body_tipo = $metodo_solicitado["body_tipo"];

                    if($body_tipo == "application/json" || $body_tipo == "multipart/form-data" || $body_tipo == "x-www-form-urlencoded") // Si el body se puede procesar
                    {
                        $body = array(); // La información definida en $_POST
                        if(isset($metodo_solicitado["body"])) // Si el método tiene campos de entrada
                        {
                            // Obtenemos los parámetros enviados por php://input
                            if($body_tipo == "application/json") // Si el body se proporcionará como JSON
                            {
                                $php_input = NULL;
                                $php_input_string = file_get_contents("php://input");
                                if($php_input_string) // Si hay parámetros de entrada mediante php://input
                                {
                                    $php_input = json_decode($php_input_string, TRUE);

                                    if($php_input) // Si se proporcionan variables como JSON
                                    {
                                        $body = OpenAPI::ProcesarBloqueDeVariables($php_input, $metodo_solicitado["body"]);
                                    }
                                }
                            }
                            else // Si el body no se proporcionará como JSON
                            {
                                if($body_tipo == "multipart/form-data" || $body_tipo == "x-www-form-urlencoded") // Si el body se proporcionará como multipart/form-data ó x-www-form-urlencoded
                                {
                                    // Obtenemos los parámetros enviados con $_POST
                                    if($_POST) // Si hay parámetros de entrada mediante $_POST
                                    {
                                        $body = OpenAPI::ProcesarBloqueDeVariables($_POST, $metodo_solicitado["body"]);
                                    }

                                    if($body_tipo == "multipart/form-data")
                                    {
                                        // Procesamos los archivos enviados
                                        foreach($_FILES as $body_nombre=>$archivo_contenedor) // Para cada archivo proporcionado
                                        {
                                            // Si el campo está definido y es un archivo
                                            if( isset($metodo_solicitado["body"][$body_nombre]) && $metodo_solicitado["body"][$body_nombre]["tipo"] === FDW_DATO_FILE )
                                            {
                                                $body[$body_nombre] = $archivo_contenedor;
                                            }
                                        }
                                    }
                                }
                            }

                            // Verificamos que todos los campos requeridos se hayan proporcionado
                            $body_no_proporcionado = OpenAPI::ValidarSiCampoDefinidoEsValido($metodo_solicitado["body"], $body);
                        }
                    }
                    else // Si el cuerpo no puede ser procesado
                    {
                        $body = file_get_contents("php://input");
                    }
                }
                $llamada["body"] = $body;
                $llamada["body_no_proporcionado"] = $body_no_proporcionado;

                $llamada["respuesta"] = $metodo_solicitado["respuesta"];
                $llamada["respuesta_tipo"] = $metodo_solicitado["respuesta_tipo"];
            }
            else // Si el método solicitado no está definido
            {
                $llamada["metodo"] = "XXX";
            }
        }

        return $llamada;
    }

    private static function ProcesarBloqueDeVariables(array $datos, array $definicion):array
    {
        $variables = array();

        foreach($definicion as $variable_nombre=>$variable_contenedor) // Para cada campo disponible
        {
            if( isset($datos[$variable_nombre]) ) // Si se proporciona el campo
            {
                if($variable_contenedor["tipo"] === FDW_DATO_OBJECT) // Si el tipo es un objeto
                {
                    if( is_array($datos[$variable_nombre]) ) // Si los datos proporcionados sí son objeto
                    {
                        $variables[$variable_nombre] = OpenAPI::ProcesarBloqueDeVariables($datos[$variable_nombre], $definicion[$variable_nombre]["propiedades"]);
                    }
                    // Si los datos proporcionados no son objeto no los procesaremos                    
                }
                else // Si el tipo no es un objeto
                {
                    if($variable_contenedor["tipo"] === FDW_DATO_ARRAY) // Si el tipo es un array
                    {
                        $variables[$variable_nombre] = array();

                        if( is_array($datos[$variable_nombre]) && count($datos[$variable_nombre]) > 0 ) // Si los datos enviados son arreglo
                        {
                            if( is_array($variable_contenedor["arreglo"]) ) // Si es un arreglo de objetos
                            {
                                foreach($datos[$variable_nombre] as $array_indice=>$array_value) // Para cada elemento del arreglo enviado
                                {
                                    $variables[$variable_nombre][$array_indice] = OpenAPI::ProcesarBloqueDeVariables($array_value, $definicion[$variable_nombre]["arreglo"]);
                                }
                            }
                            else // Si es un arreglo de tipos primitivos
                            {
                                foreach($datos[$variable_nombre] as $array_indice=>$array_value) // Para cada elemento del arreglo enviado
                                {
                                    $variables[$variable_nombre][$array_indice] = Parametro::LimpiarValor($array_value, $variable_contenedor["arreglo"]);
                                }
                            }
                        }
                    }
                    else // Si no es objeto ni array
                    {
                        $variables[$variable_nombre] = Parametro::LimpiarValor($datos[$variable_nombre], $variable_contenedor["tipo"]);
                    }
                }
            }
        }

        return $variables;
    }

    public static function ValidarRespuesta(array $request, array $estado)
    {
        $error = NULL;

        // Validamos que todos los campos del estado están definidos
        $campo_no_definido = OpenAPI::ValidarSiCampoEstaDefinido($request["respuesta"], $estado);
        if($campo_no_definido) // Si el campo no está definido
        {
            $error = "Error de implementación. El campo '{$campo_no_definido}' no está definido.";
        }
        else // Si todos los campos están definidos
        {
            // Validamos que todos los campos requeridos se proporcionen
            $campo_no_proporcionado = OpenAPI::ValidarSiCampoDefinidoEsValido($request["respuesta"], $estado);
            if($campo_no_proporcionado) // Si el campo no está definido
            {
                $error = "Error de implementación. El campo '{$campo_no_proporcionado}' no es válido o no se proporcionó.";
            }
        }

        return $error;
    }

    private static function ValidarSiCampoEstaDefinido(array $request_respuesta, $estado, string $prefijo_padre = NULL)
    {
        $campo_no_definido = NULL;

        if(is_array($estado)) // Si se proporciona un estado que es array (lo que se espera)
        {
            foreach($estado as $campo_nombre=>$campo_valor) // Para cada campo del estado
            {
                if( isset($request_respuesta[$campo_nombre]) ) // Si el campo existe en la definición
                {
                    if( is_array($campo_valor) ) // Si el campo es un array
                    {
                        $tipo_definicion = $request_respuesta[$campo_nombre]["tipo"];
                        if($tipo_definicion === FDW_DATO_OBJECT) // Si el valor es un objeto
                        {
                            $ruta_a_verificar = $prefijo_padre ? "{$prefijo_padre}.{$campo_nombre}" : $campo_nombre;

                            $campo_no_definido = OpenAPI::ValidarSiCampoEstaDefinido($request_respuesta[$campo_nombre]["propiedades"], $estado[$campo_nombre], $ruta_a_verificar);

                            if($campo_no_definido) // Si se ha encontrado un campo no definido
                            {
                                break; // Terminamos la iteración
                            }
                        }
                        else // Si el valor no es un objeto
                        {
                            if($tipo_definicion === FDW_DATO_ARRAY) // Si el valor es un arreglo
                            {
                                foreach($campo_valor as $indice=>$elemento) // Para cada elemento del arreglo
                                {
                                    if(is_array($request_respuesta[$campo_nombre]["arreglo"])) // Si es un arreglo de objetos
                                    {
                                        $ruta_a_verificar = $prefijo_padre ? "{$prefijo_padre}.{$campo_nombre}[{$indice}]" : "{$campo_nombre}[{$indice}]";

                                        $campo_no_definido = OpenAPI::ValidarSiCampoEstaDefinido($request_respuesta[$campo_nombre]["arreglo"], $elemento, $ruta_a_verificar);

                                        if($campo_no_definido) // Si se ha encontrado un campo no definido
                                        {
                                            break; // Terminamos la iteración
                                        }
                                    }
                                    else // Si es un arreglo de tipos primitivos
                                    {
                                        // No hay nada que validar aquí. Si hay error en tipos de datos será notificado después.
                                    }
                                }
                            }
                            else // Si el valor no es un arreglo (y tampoco es objeto)
                            {
                                // Esto es un error pero no es nuestra responsabilidad notificarlo.
                            }
                        }
                    }
                    else // Si el campo no es un array
                    {
                        // Esto es un error pero no es nuestra responsabilidad notificarlo.
                    }
                }
                else // Si el campo no existe en la definición
                {
                    // Si se especifica prefijo padre
                    $campo_no_definido = $prefijo_padre ?
                        "{$prefijo_padre}.{$campo_nombre}" :
                        $campo_nombre;
                    break;
                }
            }
        }
        else // Si no se proporciona un estado que es array (error)
        {
            // Esto es un error pero no es nuestra responsabilidad notificarlo.
        }

        return $campo_no_definido;
    }

    private static function ValidarSiCampoDefinidoEsValido(array $request_respuesta, $estado, string $prefijo_padre = NULL)
    {
        $campo_erroneo = NULL;

        if(is_array($estado)) // Si se proporciona un estado que es array (lo que se espera)
        {
            foreach($request_respuesta as $campo_nombre=>$campo_definicion) // Para cada campo de la definición
            {
                if( isset($estado[$campo_nombre]) ) // Si el campo existe en la respuesta
                {
                    $tipo_de_dato_definicion = $campo_definicion["tipo"];
                    $tipo_de_dato_valor = BdD::ObtenerTipoDeDato($estado[$campo_nombre]);

                    // En contenedores, el tipo-valor siempre es array
                    $definicion_es_contenedor = $tipo_de_dato_definicion === FDW_DATO_OBJECT || $tipo_de_dato_definicion === FDW_DATO_ARRAY;

                    // El tipo de dato FDW_DATO_DATETIME también puede ser FDW_DATO_DATE

                    if(
                        $tipo_de_dato_definicion == $tipo_de_dato_valor || // Si el tipo es el esperado

                        ($tipo_de_dato_definicion == FDW_DATO_DATETIME && $tipo_de_dato_valor == FDW_DATO_DATE) || // Si la definición espera un DATETIME y proporcionamos un DATE se considera válido

                        ($definicion_es_contenedor && $tipo_de_dato_valor == FDW_DATO_ARRAY) || // Si el tipo esperado es contenedor y el valor es un array
                        ($tipo_de_dato_definicion == FDW_DATO_FILE && $tipo_de_dato_valor == FDW_DATO_ARRAY) // Los tipos de dato FDW_DATO_FILE se pueden representar como array
                      )
                    {
                        if( $tipo_de_dato_definicion == FDW_DATO_OBJECT ) // Si el campo es un objeto
                        {
                            $ruta_prefijo_padre = $prefijo_padre ? "{$prefijo_padre}.{$campo_nombre}" : $campo_nombre;

                            $campo_erroneo = OpenAPI::ValidarSiCampoDefinidoEsValido($request_respuesta[$campo_nombre]["propiedades"], $estado[$campo_nombre], $ruta_prefijo_padre);
                        }
                        else // Si el campo no es un objeto
                        {
                            if( $tipo_de_dato_definicion == FDW_DATO_ARRAY ) // Si el campo es un array
                            {
                                $tipo_de_dato_definicion = $request_respuesta[$campo_nombre]["arreglo"];

                                foreach($estado[$campo_nombre] as $indice=>$elemento) // Para cada elemento del arreglo
                                {
                                    if( is_array($tipo_de_dato_definicion) ) // Si es un arreglo de objetos
                                    {
                                        $ruta_prefijo_padre = $prefijo_padre ? "{$prefijo_padre}.{$campo_nombre}[{$indice}]" : "{$campo_nombre}[{$indice}]";

                                        $campo_erroneo = OpenAPI::ValidarSiCampoDefinidoEsValido($request_respuesta[$campo_nombre]["arreglo"], $elemento, $ruta_prefijo_padre);
                                    }
                                    else // Si es un arreglo de objetos primitivos
                                    {
                                        $tipo_de_dato_valor = BdD::ObtenerTipoDeDato($elemento);

                                        if($tipo_de_dato_valor !== $tipo_de_dato_definicion) // Si el tipo de dato enviado no coincide con lo esperado
                                        {
                                            // Si se especifica prefijo padre
                                            $campo_erroneo = $prefijo_padre ?
                                                "{$prefijo_padre}.{$campo_nombre}[{$indice}]" :
                                                "{$campo_nombre}[{$indice}]";
                                        }
                                    }

                                    if($campo_erroneo) // Si hay un campo erróneo
                                    {
                                        break; // Terminamos la iteración
                                    }
                                }
                            }
                            else // Si el campo no es es array (ni objeto)
                            {
                                if($tipo_de_dato_definicion == FDW_DATO_FILE) // Si se está validando un archivo
                                {
                                    if(!isset($estado[$campo_nombre]["tmp_name"]) || !is_uploaded_file($estado[$campo_nombre]["tmp_name"])) // Si el archivo no es válido
                                    {
                                        // Si se especifica prefijo padre
                                        $campo_erroneo = $prefijo_padre ?
                                            "{$prefijo_padre}.{$campo_nombre}" :
                                            $campo_nombre;
                                        break;
                                    }
                                }
                            }
                        }
                    }
                    else // Si el tipo no es el esperado
                    {
                        // Si se especifica prefijo padre
                        $campo_erroneo = $prefijo_padre ?
                            "{$prefijo_padre}.{$campo_nombre}" :
                            $campo_nombre;
                        break;
                    }
                }
                else // Si el campo no existe en la respuesta
                {
                    if($campo_definicion["requerido"]) // Si el campo inexistente es requerido
                    {
                        // Si se especifica prefijo padre
                        $campo_erroneo = $prefijo_padre ?
                            "{$prefijo_padre}.{$campo_nombre}" :
                            $campo_nombre;
                        break;
                    }
                }
            }
        }
        else // Si no se proporciona un estado que es array (error)
        {
            $campo_erroneo = $prefijo_padre;
        }

        return $campo_erroneo;
    }
}