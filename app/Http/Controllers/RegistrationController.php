<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Contact;
use RicorocksDigitalAgency\Soap\Facades\Soap;
use Illuminate\Support\Facades\Log;

class RegistrationController extends Controller
{
    public function create()
    {
        return view('create');  // tu vista con el formulario
    }

    public function store(Request $request)
    {
        $request->validate([
            'tipo_documento'   => 'required|string', // Puedes agregar más tipos si es necesario
            'cedula'           => 'required',
            'nombres_apellidos' => 'required|string',
            'codigo_dactilar'  => 'required|string',
            'correo'           => 'required|email',
            'fecha_expiracion' => 'required|date',
        ]);

        if (Contact::where('cedula', $request->cedula)->exists()) {
            return redirect()->back()->with('error', 'Esta número de identificación ya fue registrado anteriormente.');
        }

        // Verificar si ya existe el mismo correo
        if (Contact::where('correo', $request->correo)->exists()) {
            return redirect()->back()->with('error', 'Este correo ya está registrado con número de identificación.');
        }

        Contact::create([
            'tipo_documento'    => $request->tipo_documento, // Por defecto 'cedula', pero puedes cambiarlo
            'cedula'            => $request->cedula,
            'nombres_apellidos' => $request->nombres_apellidos,
            'codigo_dactilar'   => $request->codigo_dactilar,
            'correo'            => $request->correo,
            'fecha_expiracion'  => $request->fecha_expiracion,
        ]);

        return redirect()->back()->with('success', '✅ Datos guardados exitosamente.');
    }


    /**
     * AJAX: recibe { cedula }, llama a getFichaGeneral y devuelve JSON.
     */
    public function consultarCedula(Request $request)
    {
        // 1) Validación básica
        $request->validate([
            'cedula' => ['required', 'digits:10']
        ]);

        $cedula = $request->cedula;

        try {
            // 2) Configuración SSL (si hace falta)
            $context = stream_context_create([
                'ssl' => [
                    'verify_peer'       => false,
                    'verify_peer_name'  => false,
                    'allow_self_signed' => true,
                ],
            ]);

            // 3) Llamada SOAP a getFichaGeneral
            $response = Soap::to(config('soap.dinardap_wsdl'))
                ->withBasicAuth(
                    config('soap.dinardap_user'),
                    config('soap.dinardap_pass')
                )
                ->withOptions(['stream_context' => $context])
                ->call('getFichaGeneral', [
                    'numeroIdentificacion' => $cedula,
                    'codigoPaquete'        => '471',
                ]);

            // 4) Parseo de la respuesta
            // Aquí debes inspeccionar exactamente tu $response.
            // Por ejemplo, si vienen así:
            // $response->return->instituciones->datosPrincipales->registros
            // Y suponiendo que:
            //    registros[0]->valor = cédula
            //    registros[1]->valor = nombre completo
            $body = $response->response;
            $registros = $body->return->instituciones->datosPrincipales->registros;
            foreach ($registros as $registro) {
                if (isset($registro->campo) && strtolower($registro->campo) === 'nombre') {
                    $nombre = $registro->valor;
                    break;
                }
            }

            if (! $nombre) {
                return response()->json([
                    'error'   => 'no_encontrado',
                    'message' => 'No se encontraron datos para esta cédula'
                ], 200);
            }

            return response()->json([
                'nombre' => $nombre,
                'cedula' => $cedula
            ], 200);
        } catch (\Throwable $e) {
            Log::error("❌ Dinardap getFichaGeneral error: " . $e->getMessage());
            Log::error("❌ Exception trace: " . $e->getTraceAsString());

            return response()->json([
                'error'   => 'sin_servicio',
                'message' => 'No se pudo conectar al servicio Dinardap'
            ], 200);
        }
    }

    // ... tu función validarCedulaIdentidad si aún la usas ...
}
