<?php
/**
 * Sistemas Especializados e Innovaci�n Tecnol�gica, SA de CV
 * Mpsoft.FDW - Framework de Desarrollo Web para PHP
 *
 * v.2.0.0.0 - 2020-06-28
 */
namespace Mpsoft\FDW\Core;

use Mpsoft\FDW\Dato\BdD;

/**
 * Compatibilidad de FDW con la especificaci�n OpenAPI
 */
abstract class OpenAPI
{
    public static function ObtenerLlamadaSolicitada(array $definicion_de_llamadas)
    {
        /* Obtenemos la llamada solicitada */
        $LLAMADASOLICITADA = substr($_SERVER["REQUEST_URI"], 1); // Sin / al inicio
        $INDICE_PARAMETRO = strpos($LLAMADASOLICITADA, "?"); // Quitamos los par�metros recibidos por $_GET
        if($INDICE_PARAMETRO !== FALSE) // Si hay par�metros
        {
            $LLAMADASOLICITADA = substr($LLAMADASOLICITADA, 0, $INDICE_PARAMETRO);
        }

        $llamada_solicitada = NULL;

        // Determinamos qu� llamada de la definici�n son posibles por la URL
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
        if($llamada_solicitada) // Si se obtiene la defici�n de la llamada solicitada
        {
            // Procesamos las variables de la URL
            $variable = array(); // Las variables proporcionadas en la url
            foreach($llamada_solicitada["variables"] as $variable_nombre=>$variable_tipo) // Para cada variable disponible en la definici�n
            {
                if( isset($pm_matches[$variable_nombre]) ) // Si se define la variable
                {
                    $variable[$variable_nombre] = Parametro::LimpiarValor($pm_matches[$variable_nombre], $variable_tipo);
                }
            }
            $llamada = array("variable"=>$variable);

            $llamada["script_php_ruta"] = $llamada_solicitada["script_php_ruta"];

            $REQUEST_METHOD = $_SERVER["REQUEST_METHOD"];
            if( isset($llamada_solicitada["metodos"][$REQUEST_METHOD]) ) // Si el m�todo solicitado est� definido
            {
                $llamada["metodo"] = $REQUEST_METHOD;

                $metodo_solicitado = $llamada_solicitada["metodos"][$REQUEST_METHOD];
                $llamada["autenticar"] = $metodo_solicitado["autenticar"];


                // Procesamos los valores porporcionados en $_GET
                $get = array(); // La informaci�n definida en $_GET
                $get_no_proporcionado = NULL;
                if(isset($metodo_solicitado["get"])) // Si el m�todo tiene campos en GET
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
                                break; // Terminamos, no es necesario procesar los dem�s campo.
                            }
                        }
                    }
                }
                $llamada["get"] = $get;
                $llamada["get_no_proporcionado"] = $get_no_proporcionado;

                // Procesamos los valores porporcionados en $_POST
                $body = array(); // La informaci�n definida en $_POST
                $body_no_proporcionado = NULL;
                if(isset($metodo_solicitado["body"])) // Si el m�todo tiene campos en POST
                {
                    // Obtenemos los par�metros enviados por php://input
                    $parametros_enviados_con_input = array();
                    $php_input = NULL;
                    $php_input_string = file_get_contents("php://input");
                    if($php_input_string) // Si hay par�metros de entrada mediante php://input
                    {
                        $php_input = json_decode($php_input_string, TRUE);

                        if($php_input) // Si se proporcionan variables como JSON
                        {
                            $parametros_enviados_con_input = OpenAPI::ProcesarBloqueDeVariables($php_input, $metodo_solicitado["body"]);
                        }
                    }

                    // Obtenemos los par�metros enviados con $_POST
                    $parametros_enviados_con_post = array();
                    if($_POST) // Si hay par�metros de entrada mediante $_POST
                    {
                        $parametros_enviados_con_post = OpenAPI::ProcesarBloqueDeVariables($_POST, $metodo_solicitado["body"]);
                    }

                    $body = array_merge($parametros_enviados_con_post, $parametros_enviados_con_input);

                    // Procesamos los archivos enviados
                    foreach($_FILES as $body_nombre=>$archivo_contenedor) // Para cada archivo proporcionado
                    {
                        // Si el campo est� definido y es un archivo
                        if( isset($metodo_solicitado["body"][$body_nombre]) && $metodo_solicitado["body"][$body_nombre]["tipo"] === FDW_DATO_FILE )
                        {
                            $body[$body_nombre] = $archivo_contenedor;
                        }
                    }

                    // Verificamos que todos los campos requeridos se hayan proporcionado
                    foreach($metodo_solicitado["body"] as $body_nombre=>$body_contenedor) // Para cada campo disponible
                    {
                        if($body_contenedor["requerido"] && !isset($body[$body_nombre])) // Si el campo es requerido y no se proporciona
                        {
                            $body_no_proporcionado = $body_nombre;
                            break; // Terminamos, no es necesario procesar los dem�s campo.
                        }
                    }
                }
                $llamada["body"] = $body;
                $llamada["body_no_proporcionado"] = $body_no_proporcionado;

                $llamada["respuesta"] = $metodo_solicitado["respuesta"];
                $llamada["respuesta_tipo"] = $metodo_solicitado["respuesta_tipo"];
            }
            else // Si el m�todo solicitado no est� definido
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
                    $variables[$variable_nombre] = OpenAPI::ProcesarBloqueDeVariables($datos[$variable_nombre], $definicion[$variable_nombre]["propiedades"]);
                }
                else // Si el tipo no es un objeto
                {
                    if($variable_contenedor["tipo"] === FDW_DATO_ARRAY) // Si el tipo es un array
                    {
                        if( is_array($datos[$variable_nombre]) && count($datos[$variable_nombre]) > 0 ) // Si los datos enviados son arreglo
                        {
                            $variables[$variable_nombre] = array();

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

        // Validamos que todos los campos del estado est�n definidos
        $campo_no_definido = OpenAPI::ValidarSiCampoEstaDefinido($request["respuesta"], $estado);
        if($campo_no_definido) // Si el campo no est� definido
        {
            $error = "Error de implementaci�n. El campo '{$campo_no_definido}' no est� definido.";
        }
        else // Si todos los campos est�n definidos
        {
            // Validamos que todos los campos requeridos se proporcionen
            $campo_no_proporcionado = OpenAPI::ValidarSiCampoDefinidoEsValido($request["respuesta"], $estado);
            if($campo_no_proporcionado) // Si el campo no est� definido
            {
                $error = "Error de implementaci�n. El campo '{$campo_no_proporcionado}' no es v�lido o no se proporcion�.";
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
                if( isset($request_respuesta[$campo_nombre]) ) // Si el campo existe en la definici�n
                {
                    if( is_array($campo_valor) ) // Si el campo es un array
                    {
                        $tipo_definicion = $request_respuesta[$campo_nombre]["tipo"];
                        if($tipo_definicion === FDW_DATO_OBJECT) // Si el valor es un objeto
                        {
                            $campo_no_definido = OpenAPI::ValidarSiCampoEstaDefinido($request_respuesta[$campo_nombre]["propiedades"], $estado[$campo_nombre], $campo_nombre);
                        }
                        else // Si el valor no es un objeto
                        {
                            if($tipo_definicion === FDW_DATO_ARRAY) // Si el valor es un arreglo
                            {
                                foreach($campo_valor as $indice=>$elemento) // Para cada elemento del arreglo
                                {
                                    $campo_no_definido = OpenAPI::ValidarSiCampoEstaDefinido($request_respuesta[$campo_nombre]["arreglo"], $elemento, "{$campo_nombre}[{$indice}]");

                                    if($campo_no_definido) // Si se ha encontrado un campo no definido
                                    {
                                        break; // Terminamos la iteraci�n
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
                else // Si el campo no existe en la definici�n
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
            foreach($request_respuesta as $campo_nombre=>$campo_definicion) // Para cada campo de la definici�n
            {
                if( isset($estado[$campo_nombre]) ) // Si el campo existe en la respuesta
                {
                    $tipo_de_dato_definicion = $campo_definicion["tipo"];
                    $tipo_de_dato_valor = BdD::ObtenerTipoDeDato($estado[$campo_nombre]);

                    // En contenedores, el tipo-valor siempre es array
                    $definicion_es_contenedor = $tipo_de_dato_definicion === FDW_DATO_OBJECT || $tipo_de_dato_definicion === FDW_DATO_ARRAY;

                    if(
                        $tipo_de_dato_definicion == $tipo_de_dato_valor || // Si el tipo es el esperado
                        ($definicion_es_contenedor && $tipo_de_dato_valor == FDW_DATO_ARRAY) // Si el tipo esperado es contenedor y el valor es un array
                      )
                    {
                        if( $tipo_de_dato_definicion == FDW_DATO_OBJECT ) // Si el campo es un objeto
                        {
                            $campo_erroneo = OpenAPI::ValidarSiCampoDefinidoEsValido($request_respuesta[$campo_nombre]["propiedades"], $estado[$campo_nombre], $campo_nombre);
                        }
                        else // Si el campo no es un objeto
                        {
                            if( $tipo_de_dato_definicion == FDW_DATO_ARRAY ) // Si el campo es un array
                            {
                                foreach($estado[$campo_nombre] as $indice=>$elemento) // Para cada elemento del arreglo
                                {
                                    $campo_erroneo = OpenAPI::ValidarSiCampoDefinidoEsValido($request_respuesta[$campo_nombre]["arreglo"], $elemento, "{$campo_nombre}[{$indice}]");

                                    if($campo_erroneo) // Si hay un campo err�neo
                                    {
                                        break; // Terminamos la iteraci�n
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