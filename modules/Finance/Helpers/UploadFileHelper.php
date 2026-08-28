<?php

namespace Modules\Finance\Helpers; 

use Illuminate\Support\Facades\Storage;
use Validator;
use Illuminate\Support\Str;
use Exception;
use Symfony\Component\HttpFoundation\File\File;
use Intervention\Image\ImageManagerStatic as Image;
use Illuminate\Support\Facades\Log;


class UploadFileHelper
{ 
    
    /**
     * 
     * Validar archivos
     *
     * @param  Request $request
     * @param  string $column
     * @param  string $mimes
     * @param  bool $is_image
     * @param  int|null $max_kb  Tamaño máximo permitido en kilobytes (null = sin limite)
     * @return array
     */
    public static function validateUploadFile($request, $column = 'file', $mimes = 'jpg,jpeg,png,gif,svg,webp,pdf,xlsx', $is_image = true, $max_kb = null)
    {

        $rules = ['mimes:'.$mimes];

        if($max_kb) $rules[] = 'max:'.$max_kb;

        $validator = Validator::make($request->all(), [
            $column => implode('|', $rules)
        ]);

        if ($validator->fails()) { 

            $file = $request->file($column);

            // Si el archivo supera los límites de php (upload_max_filesize/post_max_size)
            // no llega nada en la petición, por lo que el error de mimes es engañoso
            if(!$file)
            {
                return [
                    'success' => false,
                    'message' =>  'No se recibió el archivo, verifique que no exceda el tamaño máximo permitido',
                ];
            }

            if($max_kb && $file->getSize() > ($max_kb * 1024))
            {
                return [
                    'success' => false,
                    'message' =>  'El archivo excede el tamaño máximo permitido de '.self::formatMaxSize($max_kb),
                ];
            }

            return [
                'success' => false,
                'message' =>  'Tipo de archivo no permitido',
            ];
        }

        if($is_image)
        {
            $processed = self::imageCanBeProcessed($request->file($column)->getPathName());
            if(!$processed['success']) return $processed;
        }
        

        return [
            'success' => true,
            'message' =>  '',
        ];

    } 

     
    /**
     * 
     * Formato legible del tamaño máximo permitido
     *
     * @param  int $max_kb
     * @return string
     */
    private static function formatMaxSize($max_kb)
    {
        return ($max_kb >= 1024) ? (round($max_kb / 1024, 1).'MB') : ($max_kb.'KB');
    }


    /**
     * 
     * Obtener archivo temporal en base64
     *
     * @param  $request
     * @return array
     */
    public static function getTempFile($request)
    {
        $file = $request['file'];
        $type = $request['type'];

        $temp = tempnam(sys_get_temp_dir(), $type);
        file_put_contents($temp, file_get_contents($file));

        $mime = mime_content_type($temp);
        $data = file_get_contents($temp);

        return [
            'success' => true,
            'data' => [
                'filename' => $file->getClientOriginalName(),
                'temp_path' => $temp,
                'temp_image' => 'data:' . $mime . ';base64,' . base64_encode($data)
            ]
        ];
    }

    
    /**
     * 
     * Cargar archivo
     *
     * Usado para imágenes en:
     * PaymentConfigurationController
     * PaymentLinkController
     * 
     * @param  string $folder
     * @param  string $old_filename
     * @param  string $temp_path
     * @param  int $id
     * @param  string $prefix
     * @return string
     */
    public static function uploadFileFromTempFile($folder, $old_filename, $temp_path, $id, $prefix = null, $mimes = 'jpg,jpeg,png,svg,webp', $allowed_file_types = ['image/jpg', 'image/jpeg', 'image/png', 'image/svg', 'image/webp'])
    {

        $directory = 'public'.DIRECTORY_SEPARATOR.'uploads'.DIRECTORY_SEPARATOR.$folder.DIRECTORY_SEPARATOR;
        $old_filename_array = explode('.', $old_filename);
        $now = date('YmdHis');

        $filename =  ($prefix ? "{$prefix}_" : "")."{$id}_{$now}".'.'.end($old_filename_array);

        self::checkIfValidFile($filename, $temp_path, true, $mimes, $allowed_file_types);

        Storage::put($directory.$filename, file_get_contents($temp_path));

        return $filename;
    }

    
    /**
     * 
     * Cargar imágen
     *
     * @param  string $folder
     * @param  string $old_filename
     * @param  string $temp_path
     * @param  string $name
     * @param  bool $file_get_contents
     * @param  string $suffix
     * @param  string $mimes
     * @param  array $allowed_file_types
     * @return string
     */
    public static function uploadImageFromTempFile($folder, $old_filename, $temp_path, $name, $file_get_contents, $suffix = null, $mimes = 'jpg,jpeg,png,svg,webp', $allowed_file_types = ['image/jpg', 'image/jpeg', 'image/png', 'image/svg', 'image/webp'])
    {
        
        $directory = 'public'.DIRECTORY_SEPARATOR.'uploads'.DIRECTORY_SEPARATOR.$folder.DIRECTORY_SEPARATOR;
        $old_filename_array = explode('.', $old_filename);
        $now = date('YmdHis');

        $suffix = ($suffix ? "-{$suffix}" : "");
        $filename =  Str::slug($name)."-{$now}{$suffix}".'.'.end($old_filename_array);

        $file = $file_get_contents ? file_get_contents($temp_path) :  $temp_path;

        if($file_get_contents)  
        {
            self::checkIfValidFile($filename, $temp_path, true, $mimes, $allowed_file_types);
        }
        else
        {
            self::checkIfImageCanBeProcessed($temp_path);
        }

        Storage::put($directory.$filename, $file);

        return $filename;
    }


    /**
     * 
     * lanza excepcion si el archivo no es permitido
     *
     * @param  string $message
     * @return void
     */
    public static function notAllowedFile($message)
    {
        throw new Exception($message);
    }

    
    /**
     * 
     * Validar si es un archivo válido
     *
     * @param  string $filename
     * @param  string $temp_path
     * @param  bool $is_image
     * @param  string $mimes
     * @param  array $allowed_file_types
     * @return void
     */
    public static function checkIfValidFile($filename, $temp_path, $is_image = true, $mimes = null, $allowed_file_types = null)
    {
        $error_message = 'Tipo de archivo no permitido';
        $mimes = $mimes ?? self::getGeneralMimes();
        $allowed_file_types = $allowed_file_types ?? self::getGeneralAllowedFileTypes();

        self::checkIfAllowedExtension($filename, $mimes);

        if (!in_array(mime_content_type($temp_path), $allowed_file_types, true)) self::notAllowedFile($error_message);

        $new_file = new File($temp_path);

        $data = [
            'file' => $new_file
        ];

        $validator = Validator::make($data, [
            'file' => 'mimes:'.$mimes
        ]);

        if($validator->fails()) self::notAllowedFile($error_message);

        if($is_image) self::checkIfImageCanBeProcessed($temp_path);
    }

    
    /**
     * 
     * Validar si es un archivo css válido
     *
     * @param  string $filename
     * @param  string $temp_path
     * @param  string $mimes
     * @param  array $allowed_file_types
     * @return void
     */
    public static function checkIfValidCssFile($filename, $temp_path, $mimes, $allowed_file_types)
    {
        $error_message = 'Tipo de archivo no permitido';

        self::checkIfAllowedExtension($filename, $mimes);

        if (!in_array(mime_content_type($temp_path), $allowed_file_types, true)) self::notAllowedFile($error_message);
    }
    

    /**
     * 
     * Determina si es imagen
     *
     * @param  string $temp_path
     * @param  array $allowed_file_types
     * @return bool
     */
    public static function getIsImage($temp_path, $allowed_file_types)
    {
        return in_array(mime_content_type($temp_path), $allowed_file_types, true);
    }

        
    /**
     * Validar si la imagen pudo ser procesada
     *
     * @param  string $temp_path
     * @return void
     */
    public static function checkIfImageCanBeProcessed($temp_path)
    {
        $processed = self::imageCanBeProcessed($temp_path);

        if(!$processed['success']) self::notAllowedFile($processed['message']);
    }

    
    /**
     * 
     * Procesar imagen
     *
     * @param  string $temp_path
     * @return array
     */
    public static function imageCanBeProcessed($temp_path)
    {
        try 
        {
            Image::make($temp_path);
            
            return [
                'success' => true,
            ];
        }
        catch (Exception $e) 
        {
            $message = 'La imágen no puede ser procesada, verifique si es un archivo válido.';
            self::writeErrorLog($e, $message);

            return [
                'success' => false,
                'message' => $message,
            ];
        }
    }

     /**
     * Optimiza imagen (raster) para que el resultado JPG pese ~targetBytes.
     * Se usa principalmente para logos que luego se incrustan como base64 en PDFs.
     *
     * @param string $absolutePath Ruta absoluta al archivo original
     * @param int $maxWidth Máximo ancho final (mantiene proporción)
     * @param int $targetBytes Objetivo en bytes del JPG codificado
     * @param int $qualityStart Calidad inicial JPG
     * @param int $qualityMin Calidad mínima JPG
     * @return array|null ['bytes' => string, 'quality' => int] o null si falla
     */
    public static function optimizeRasterImageToTargetJpg(
        string $absolutePath,
        int $maxWidth,
        int $targetBytes,
        int $qualityStart = 80,
        int $qualityMin = 20
    ): ?array {
        try {
            $image = Image::make($absolutePath);

            // Limitar tamaño físico para que el base64 del PDF no se dispare.
            $image->resize($maxWidth, null, function ($constraint) {
                $constraint->aspectRatio();
                $constraint->upsize();
            });

            if (self::imageSupportsTransparency($absolutePath)) {
                $encoded = (string) $image->encode('png');
                if ($encoded === '') return null;

                return [
                    'bytes' => $encoded,
                    'quality' => 100,
                    'extension' => 'png',
                ];
            }

            $bestBytes = '';
            $bestQuality = $qualityStart;

            // Ajustar calidad en pasos para acercarnos al target (sin adivinar).
            for ($q = $qualityStart; $q >= $qualityMin; $q -= 5) {
                $encoded = (string) $image->encode('jpg', $q);
                if (strlen($encoded) > 0) {
                    $bestBytes = $encoded;
                    $bestQuality = $q;
                }

                if (strlen($encoded) <= $targetBytes) {
                    break;
                }
            }

            if ($bestBytes === '') return null;

            return [
                'bytes' => $bestBytes,
                'quality' => $bestQuality,
                'extension' => 'jpg',
            ];
        } catch (Exception $e) {
            self::writeErrorLog($e, 'optimizeRasterImageToTargetJpg');
            return null;
        }
    }

    /**
     * Indica si el archivo es de un formato que puede contener transparencia (canal alfa).
     * Se usa para conservar el logo como PNG y no aplanar la transparencia al convertir a JPG.
     */
    public static function imageSupportsTransparency(string $absolutePath): bool
    {
        $mime = @mime_content_type($absolutePath) ?: '';

        return str_contains($mime, 'png')
            || str_contains($mime, 'webp')
            || str_contains($mime, 'gif');
    }
    
    /**
     *
     * @param  string $filename
     * @param  array $mimes
     * @return void
     */
    public static function checkIfAllowedExtension($filename, $mimes)
    {
        $extension = self::getFileExtension($filename);
        $allowed_extensions = explode(',', $mimes);

        if (!in_array($extension, $allowed_extensions, true)) self::notAllowedFile('Extensión del archivo no permitida.');
    }
    
    
    /**
     *
     * @param  string $filename
     * @return string
     */
    public static function getFileExtension($filename)
    {
        $data = explode('.', $filename);

        return strtolower((string) end($data));
    }


    /**
     * Resuelve la extensión permitida usando el nombre y, si hace falta, el MIME del archivo.
     *
     * @param  string $filename
     * @param  string $temp_path
     * @param  string $mimes
     * @return string
     */
    public static function resolveExtensionFromFile($filename, $temp_path, $mimes = 'jpg,jpeg,png,gif,svg,webp,pdf')
    {
        $allowed_extensions = explode(',', $mimes);
        $extension = self::getFileExtension($filename);

        if (in_array($extension, $allowed_extensions, true)) {
            return $extension;
        }

        $mime = @mime_content_type($temp_path) ?: '';
        $mime_map = [
            'image/jpeg' => 'jpg',
            'image/jpg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/svg+xml' => 'svg',
            'image/webp' => 'webp',
            'application/pdf' => 'pdf',
        ];

        if (isset($mime_map[$mime]) && in_array($mime_map[$mime], $allowed_extensions, true)) {
            return $mime_map[$mime];
        }

        return $extension;
    }


    /**
     *
     * @return string
     */
    public static function getGeneralMimes()
    {
        return 'jpg,jpeg,png,gif,svg,webp';
    }


    /**
     *
     * @return array
     */
    public static function getGeneralAllowedFileTypes()
    {
        return ['image/jpg', 'image/jpeg', 'image/png', 'image/gif', 'image/svg', 'image/webp'];
    }

    
    /**
     *
     * @param  Exception $exception
     * @return void
     */
    public static function writeErrorLog($exception, $message = null)
    {
        Log::error(($message ?? '')."Line: {$exception->getLine()} - Message: {$exception->getMessage()} - File: {$exception->getFile()}");
    }
    
}
