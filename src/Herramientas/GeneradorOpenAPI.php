<?php
/**
 * Sistemas Especializados e Innovación Tecnológica, SA de CV
 * Mpsoft.FDW - Framework de Desarrollo Web para PHP
 *
 * v.2.0.0.0 - 2023-08-30
 */
namespace Mpsoft\FDW\Herramientas;

use \Exception;

class GeneradorOpenAPI
{
    private static $openapi = NULL;

    public static function GenerarDesdeJSON(string $openapi_ruta):array
    {
        $openapi_json = file_get_contents($openapi_ruta);
        self::$openapi = json_decode($openapi_json, TRUE);

        $llamadas_disponibles = array();

        foreach (self::$openapi["paths"] as $ruta => $metodos) // Para cada ruta existente
        {
            // Quitamos / al inicio y al final.
            $ruta = trim($ruta);
            $ruta = trim($ruta, "/");

            foreach ($metodos as $metodo => $parametros) // Para cada método de la ruta
            {
                // ********** Obtenemos el método **********
                $metodo = strtoupper($metodo);


                // ********** Generamos la URL **********
                $ruta_para_url = str_replace("/", "\/", $ruta);
                $url = preg_replace('/\{(\w+)\}/', '(?<$1>[a-zA-Z0-9+_.-]+)', $ruta_para_url);
                $url = "/^{$url}$/U";


                // ********** Generamos el script_php_ruta **********
                $ruta_elementos_disponibles = explode("/", $ruta);
                $ruta_elementos = array();

                // Quitamos las variables
                foreach ($ruta_elementos_disponibles as $ruta_elemento) // Para cada elemento de la ruta
                {
                    if ($ruta_elemento[0] != "{") // Si no es variable
                    {
                        $ruta_elementos[] = $ruta_elemento;
                    }
                }

                if (count($ruta_elementos) == 0) // Si no hay elementos para generar la URL
                {
                    $ruta_elementos[] = "raiz";
                }

                $script_php_ruta = NULL;

                if (count($ruta_elementos) >= 1) // Si hay elementos para generar la URL
                {
                    $script_php_ruta = implode("/", $ruta_elementos);
                }
                else // Si no hay elementos para generar la URL
                {
                    $script_php_ruta = "raiz";
                }



                // ********** Procesamos las variables **********
                $variables = array();
                $get = array();
                foreach ($parametros["parameters"] as $parameter) // Para cada variable en la URL
                {
                    $nombre = $parameter["name"];

                    $tipo_de_dato_swagger = $parameter["schema"]["type"];
                    $format_de_dato_swagger = isset($parameter["schema"]["format"]) ? $parameter["schema"]["format"] : NULL;
                    $tipo_de_dato_fdw = ObtenerTipoDeDatoFDW($tipo_de_dato_swagger, $format_de_dato_swagger);

                    if ($parameter["in"] == "path") // Si es una variable de URL
                    {
                        $variables[$nombre] = array("tipo" => $tipo_de_dato_fdw);
                    }
                    else
                    {
                        if ($parameter["in"] == "query") // Si es una variable de query-string
                        {
                            $get_contenedor = array("tipo" => $tipo_de_dato_fdw, "requerido" => $parameter["required"]);

                            $valor_por_defecto = isset($parameter["default"]) ? $parameter["default"] : NULL;
                            if ($valor_por_defecto) // Si se proporciona valor por defecto
                            {
                                $get_contenedor["default"] = $valor_por_defecto;
                            }

                            $get[$nombre] = $get_contenedor;
                        }
                    }
                }


                // ********** Procesamos la autenticación **********
                $autenticar = TRUE;
                if (isset($parametros["security"]) && count($parametros["security"]) == 0) // Si es una llamada pública
                {
                    $autenticar = FALSE;
                }

                $body = NULL;
                $body_tipo = NULL;
                if ($metodo == "POST" || $metodo == "PATCH") // Si es un método que puede tener body
                {
                    $body_tipo = "application/json";
                    if (isset($parametros["requestBody"])) // Si se solicitan datos en body
                    {
                        $content = $parametros["requestBody"]["content"];

                        $body_tipo = array_key_first($content);

                        if ($body_tipo == "application/json") // Si se solicitan datos como JSON
                        {
                            $body_schema_name = $content["application/json"]["schema"]['$ref'];

                            $body = self::ObtenerCamposDeSchema($body_schema_name);
                        }
                        else // Si no se solicitan datos como JSON
                        {
                            if ($body_tipo == "multipart/form-data") // Si se solicitan datos como form-data
                            {
                                $body_schema_name = $content["multipart/form-data"]["schema"]['$ref'];

                                $body = self::ObtenerCamposDeSchema($body_schema_name);
                            }
                            else // Si no se solicitan datos como form-data
                            {
                                // Asumimos que el tipo es RAW y lo pasaremos sin procesar
                            }
                        }
                    }
                }

                $respuesta = NULL;
                $respuesta_tipo = NULL;
                if (isset($parametros["responses"])) // Si se solicitan datos en body
                {
                    $content = $parametros["responses"][200]["content"];

                    $respuesta_tipo = array_key_first($content);

                    if ($respuesta_tipo === "application/json") // Si la respuesta de la llamada es JSON
                    {
                        $response_schema_name = $content["application/json"]["schema"]['$ref'];

                        if (!$response_schema_name)
                        {
                            throw new Exception("{$metodo}:{$ruta} - Sin response");
                        }

                        $respuesta = self::ObtenerCamposDeSchema($response_schema_name);
                    }
                    else // Si la respuesta de la llamada no es JSON
                    {
                        $respuesta = array
                        (
                            "estado" => array("tipo" => 3, "requerido" => TRUE),
                            "mensaje" => array("tipo" => 6, "requerido" => TRUE),
                            "resultado" => array("tipo" => 6, "requerido" => TRUE)
                        );
                    }
                }

                $llamada = array
                (
                    "autenticar" => $autenticar,
                    "get" => $get,
                    "body" => $body,
                    "body_tipo" => $body_tipo,
                    "respuesta" => $respuesta,
                    "respuesta_tipo" => $respuesta_tipo
                );

                if (!isset($llamadas_disponibles[$url])) // Si es la primer llamada de la URL
                {
                    $llamadas_disponibles[$url] = array("script_php_ruta" => $script_php_ruta, "variables" => $variables, "metodos" => array());
                }

                $llamadas_disponibles[$url]["metodos"][$metodo] = $llamada;
            }
        }

        return $llamadas_disponibles;
    }

    private static function ObtenerSchema(string $schema_name):array
    {
        $schema_name = str_replace("#/components/schemas/", "", $schema_name);

        return self::$openapi["components"]["schemas"][$schema_name];
    }

    private static function ObtenerTipoDeDatoFDW(string $tipo_de_dato_swagger, ?string $formato_de_dato_swagger = NULL):int
    {
        $tipo_de_dato_fdw = NULL;
        switch ($tipo_de_dato_swagger)
        {
            case "number":
                if ($formato_de_dato_swagger == "float" || $formato_de_dato_swagger == "double")
                {
                    $tipo_de_dato_fdw = FDW_DATO_DOUBLE;
                }
                else
                {
                    $tipo_de_dato_fdw = FDW_DATO_INT;
                }
                break;

            case "integer":
                $tipo_de_dato_fdw = FDW_DATO_INT;
                break;

            case "boolean":
                $tipo_de_dato_fdw = FDW_DATO_BOOL;
                break;

            case "object":
                $tipo_de_dato_fdw = FDW_DATO_OBJECT;
                break;

            case "array":
                $tipo_de_dato_fdw = FDW_DATO_ARRAY;
                break;

            case "string":
                switch ($formato_de_dato_swagger)
                {
                    case "binary":
                        $tipo_de_dato_fdw = FDW_DATO_FILE;
                        break;
                    case "date":
                        $tipo_de_dato_fdw = FDW_DATO_DATE;
                        break;
                    case "date-time":
                        $tipo_de_dato_fdw = FDW_DATO_DATETIME;
                        break;
                    default:
                        $tipo_de_dato_fdw = FDW_DATO_STRING;
                        break;
                }
                break;
        }

        return $tipo_de_dato_fdw;
    }

    private static function ObtenerCamposDeSchema(string $schema_name):array
    {
        $schema = self::ObtenerSchema($schema_name);

        $campos = self::ObtenerCamposDeObjeto($schema);

        return $campos;
    }

    private static function ObtenerCamposDeObjeto(array $schema)
    {
        $campos_requeridos = isset($schema["required"]) ? $schema["required"] : array();

        $campos = array();

        if (isset($schema["allOf"])) // Si tiene schemas padres
        {
            foreach ($schema["allOf"] as $schema_incluido) // Para cada schema padre
            {
                $campos_heredados = self::ObtenerCamposDeSchema($schema_incluido['$ref']);

                $campos = array_merge($campos, $campos_heredados);
            }

            // Los campos hijos pueden ser opcionales. Revisamos si son requeridos en el padre.
            foreach ($campos as $campo_nombre => $contenedor)
            {
                if (in_array($campo_nombre, $campos_requeridos)) // Si es un campo requerido en el padre
                {
                    $campos[$campo_nombre]["requerido"] = TRUE;
                }
            }
        }

        if (isset($schema["properties"])) // Si tiene campos
        {
            foreach ($schema["properties"] as $campo_nombre => $campo_parametros) // Para cada campo del schema
            {
                $requerido = in_array($campo_nombre, $campos_requeridos);

                if (isset($campo_parametros["type"])) // Si es un campo
                {
                    $formato_de_dato_swagger = isset($campo_parametros["format"]) ? $campo_parametros["format"] : NULL;
                    $tipo = self::ObtenerTipoDeDatoFDW($campo_parametros["type"], $formato_de_dato_swagger);

                    $info_campo = array("tipo" => $tipo, "requerido" => $requerido);

                    if ($tipo == FDW_DATO_OBJECT) // Si es un objeto
                    {
                        $info_campo["propiedades"] = self::ObtenerCamposDeObjeto($campo_parametros);
                    }

                    if ($tipo == FDW_DATO_ARRAY) // Si es un array
                    {
                        $campos_del_arreglo = array();
                        $tipo_del_arreglo = NULL;

                        if (isset($campo_parametros["items"]['$ref'])) // Si tiene un Schema
                        {
                            $campos_del_arreglo = self::ObtenerCamposDeSchema($campo_parametros["items"]['$ref']);
                        }
                        else // Si no tiene un Schema
                        {
                            $es_allOf = isset($campo_parametros["items"]['allOf']);
                            $es_anyOf = isset($campo_parametros["items"]['anyOf']);

                            if ($es_allOf || $es_anyOf) // Si tiene la unión de varios Schemas
                            {
                                $xOf = $es_allOf ? $campo_parametros["items"]['allOf'] : $campo_parametros["items"]['anyOf'];

                                foreach ($xOf as $item) // Para cada item del array
                                {
                                    $campos_esquema = self::ObtenerCamposDeSchema($item['$ref']);

                                    if($es_anyOf) // Si es anyOf los campos deben ser opcionales y validados por la llamada
                                    {
                                        foreach($campos_esquema as $indice=>$campo) // Para cada campo anyOf
                                        {
                                            $campos_esquema[$indice]["requerido"] = FALSE;
                                        }
                                    }

                                    $campos_del_arreglo = array_merge($campos_del_arreglo, $campos_esquema);
                                }
                            }
                            else // Si no tiene Schemas
                            {
                                if (isset($campo_parametros["items"]['type'])) // Si se especifica el tipo del arreglo (arreglo de tipo primitivo)
                                {
                                    $tipo_del_arreglo = self::ObtenerTipoDeDatoFDW($campo_parametros["items"]['type']);
                                }
                                else // Si no se especifica esquemas ni tipo
                                {
                                    throw new Exception("Se esperaba que el array tuviera al menos un schema o se especificara un tipo primitivo.");
                                }

                            }
                        }

                        $info_campo["arreglo"] = $tipo_del_arreglo ? $tipo_del_arreglo : $campos_del_arreglo;
                    }

                    if (isset($campos[$campo_nombre])) // Si el campo está definido por el padre
                    {
                        if ($campos[$campo_nombre]["tipo"] == $tipo) // Si el tipo del padre coincide con el tipo del hijo
                        {
                            if ($tipo == FDW_DATO_OBJECT) // Si es un objeto
                            {
                                $campos[$campo_nombre]["propiedades"] = array_merge($campos[$campo_nombre]["propiedades"], $info_campo["propiedades"]);
                            }
                            else
                            {
                                throw new Exception("Se esperaba un FDW_DATO_OBJECT");
                            }
                        }
                        else // Si el tipo del padre ha cambiado con respecto al hijo
                        {
                            $campos[$campo_nombre] = $info_campo;
                        }
                    }
                    else // Si el campo no está definido por el padre
                    {
                        $campos[$campo_nombre] = $info_campo;
                    }
                }
                else // Si no es un campo  (Si es un objeto)
                {
                    if ($campo_parametros['$ref']) // Si se cuenta con la referencia del Schema
                    {
                        $nuevas_propiedades = self::ObtenerCamposDeSchema($campo_parametros['$ref']);

                        if (isset($campos[$campo_nombre])) // Si el campo ya existe
                        {
                            if ($campos[$campo_nombre]["tipo"] === FDW_DATO_OBJECT) // Si el tipo actual es objeto
                            {
                                $campos[$campo_nombre]["requerido"] = $requerido;

                                $campos[$campo_nombre]["propiedades"] = array_merge($campos[$campo_nombre]["propiedades"], $nuevas_propiedades);
                            }
                            else // Si el tipo actual no es objeto
                            {
                                // Remplazamos el tipo
                                $campos[$campo_nombre] = array("tipo" => FDW_DATO_OBJECT, "requerido" => $requerido, "propiedades" => $nuevas_propiedades);
                            }
                        }
                        else // Si el campo aún no existe
                        {
                            $campos[$campo_nombre] = array("tipo" => FDW_DATO_OBJECT, "requerido" => $requerido, "propiedades" => $nuevas_propiedades);
                        }
                    }
                    else // Si no es objeto y tampoco es Schema
                    {
                        throw new Exception("{$campo_nombre} NO IMPLEMENTADO - No es schema ni propiedad");
                    }
                }
            }
        }

        return $campos;
    }

    public static function RepresentarComoArray($var):string
    {
        $str = "";

        if (is_array($var)) // Si la variable a representar es arreglo
        {
            $sub_str = "";
            foreach ($var as $i => $v) // Para cada elemento del array
            {
                $sub_str .= "\"{$i}\"=>" . self::RepresentarComoArray($v) . ", ";
            }
            $sub_str = substr($sub_str, 0, -2);

            $str .= "array({$sub_str})";
        }
        else // Si la variable a representar es un valor
        {
            switch (gettype($var))
            {
                case "boolean":
                    $str = $var ? "TRUE" : "FALSE";
                    break;

                case "double":
                case "integer":
                    $str = "{$var}";
                    break;

                case "string":
                    $str = "\"{$var}\"";
                    break;

                case "NULL":
                    $str = "NULL";
                    break;
            }
        }

        return $str;
    }
}