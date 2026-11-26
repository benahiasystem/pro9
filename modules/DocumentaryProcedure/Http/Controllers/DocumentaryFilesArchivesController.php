<?php

    namespace Modules\DocumentaryProcedure\Http\Controllers;

    use Illuminate\Routing\Controller;
    use Illuminate\Support\Facades\Storage;
    use Modules\DocumentaryProcedure\Models\DocumentaryFilesArchives;

    class DocumentaryFilesArchivesController extends Controller {

        /**
         * @param \Modules\DocumentaryProcedure\Models\DocumentaryFilesArchives $id
         *
         * @return \Symfony\Component\HttpFoundation\StreamedResponse
         */
        public function download(DocumentaryFilesArchives $id) {
            // ########## INICIO MIGRACIÓN ALMACENAMIENTO S3 CLOUD-ONPREMISE
            $e = app(\App\Services\Storage\TenantStorage::class)->disk()->exists($id->getAttachedFile());
            // ######### FIN MIGRACIÓN ALMACENAMIENTO S3 CLOUD-ONPREMISE
            if ($e) {
                return app(\App\Services\Storage\TenantStorage::class)->disk()->download($id->getAttachedFile(), $id->getPublicName());

            }
            abort(404);

        }

        /**
         * @param \Modules\DocumentaryProcedure\Models\DocumentaryFilesArchives $id
         *
         * @return \Illuminate\Http\JsonResponse
         * @throws \Exception
         */
        public function destroy(DocumentaryFilesArchives $id) {
            $file = $id->getAttachedFile();
            $data = [
                'success' => false,
                'file' => $file,
            ];
            // ########## INICIO MIGRACIÓN ALMACENAMIENTO S3 CLOUD-ONPREMISE
            $e = app(\App\Services\Storage\TenantStorage::class)->disk()->exists($file);
            // ######### FIN MIGRACIÓN ALMACENAMIENTO S3 CLOUD-ONPREMISE
            if ($e) {
                // ########## INICIO MIGRACIÓN ALMACENAMIENTO S3 CLOUD-ONPREMISE
                app(\App\Services\Storage\TenantStorage::class)->disk()->delete($file);
                // ######### FIN MIGRACIÓN ALMACENAMIENTO S3 CLOUD-ONPREMISE
                $id->delete();
                $data['success'] = true;
                $data['message'] = 'El registro se ha borrado';
            } else {
                if($id != null) {
                    $id->delete();
                }
                $data['success'] = true;
                $data['message'] = "El archivo no existe";
            }

            return json_encode($data);
        }

    }
