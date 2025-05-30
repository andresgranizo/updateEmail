<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Contact;
use RicorocksDigitalAgency\Soap\Facades\Soap;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class RegistrationController extends Controller
{
    public function create()
    {
        return view('create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'tipo_documento'    => 'required|string',
            'cedula'            => 'required',
            'nombres_apellidos' => 'required|string',
            'codigo_dactilar'   => 'required|string',
            'correo' => [
                'required',
                'email',
                'regex:/^[^\s,]+@[^\s,]+\.[^\s,]+$/'
            ],
            'fecha_expiracion'  => 'required|date',
        ], [
            'correo.regex'    => 'El correo no debe contener espacios ni comas.',
            'correo.email'    => 'El correo debe tener un formato válido.',
            'correo.required' => 'El campo correo electrónico es obligatorio.',
        ], [
            'tipo_documento'    => 'tipo de documento',
            'cedula'            => 'cédula',
            'nombres_apellidos' => 'apellidos y nombres',
            'codigo_dactilar'   => 'código dactilar',
            'correo'            => 'correo electrónico',
            'fecha_expiracion'  => 'fecha de expiración',
        ]);

        if ($request->tipo_documento === 'cedula') {
            $codigoDinardap = session('dinardap_codigo_dactilar');
            $fechaDinardap  = session('dinardap_fecha_expiracion');

            // Solo validar si Dinardap devolvió ambos datos
            if (!empty($fechaDinardap) && !empty($codigoDinardap)) {
                $fechaDinardapFormateada = Carbon::createFromFormat('d/m/Y', $fechaDinardap)->startOfDay();
                $fechaFormulario = Carbon::parse($request->fecha_expiracion)->startOfDay();

                if (
                    $codigoDinardap !== strtoupper($request->codigo_dactilar) ||
                    !$fechaDinardapFormateada->equalTo($fechaFormulario)
                ) {
                    return redirect()->back()
                        ->with('error', 'El código dactilar o la fecha de expiración no coinciden con los datos del Registro Civil.')
                        ->withInput();
                }
            } else {
                // No hay datos suficientes para validar contra Dinardap
                Log::info("🟡 No se realizó validación Dinardap para la cédula: " . $request->cedula);
            }
        }

        if (Contact::where('cedula', $request->cedula)->exists()) {
            return redirect()->back()
                ->with('error', 'Este número de identificación ya fue registrado anteriormente.')
                ->withInput();
        }

        if (Contact::where('correo', $request->correo)->exists()) {
            return redirect()->back()
                ->with('error', 'Este correo ya está registrado con otro número de identificación.')
                ->withInput();
        }

        Contact::create([
            'tipo_documento'    => $request->tipo_documento,
            'cedula'            => $request->cedula,
            'nombres_apellidos' => $request->nombres_apellidos,
            'codigo_dactilar'   => $request->codigo_dactilar,
            'correo'            => $request->correo,
            'fecha_expiracion'  => $request->fecha_expiracion,
        ]);

        session()->forget(['dinardap_codigo_dactilar', 'dinardap_fecha_expiracion']);

        return redirect()->back()->with('success', '✅ Datos guardados exitosamente.');
    }

    public function consultarCedula(Request $request)
    {
        $request->validate([
            'cedula' => ['required', 'digits:10']
        ]);

        $cedula = $request->cedula;

        try {
            $context = stream_context_create([
                'ssl' => [
                    'verify_peer'       => false,
                    'verify_peer_name'  => false,
                    'allow_self_signed' => true,
                ],
            ]);

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

            $body = $response->response;
            $registros = $body->return->instituciones->datosPrincipales->registros ?? [];

            $nombre = collect($registros)->firstWhere('campo', 'nombre')->valor ?? null;
            $codigoDactilar = collect($registros)->firstWhere('campo', 'individualDactilar')->valor ?? null;
            $fechaExpiracion = collect($registros)->firstWhere('campo', 'fechaExpiracion')->valor ?? null;

            session([
                'dinardap_codigo_dactilar'  => $codigoDactilar,
                'dinardap_fecha_expiracion' => $fechaExpiracion,
            ]);

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
}
