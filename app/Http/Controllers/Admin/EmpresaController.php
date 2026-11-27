<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\ConvenioEstado;
use App\Enums\TipoOrganismo;
use App\Http\Controllers\Controller;
use App\Models\Empresa;
use App\Rules\UnicoNormalizado;
use App\Services\Auditoria;
use App\Support\Formatos;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Organizaciones registradas: ficha, situación frente al CUCSH, reporte en PDF y activar o desactivar la cuenta.
 */
class EmpresaController extends Controller
{
    public function index(Request $request): View
    {
        $empresas = $this->consulta($request)
            ->paginate(15)
            ->withQueryString();

        $conVigente = Empresa::whereHas('convenios', fn ($c) => $c->vigentes())->count();
        $total = Empresa::count();

        return view('admin.lista-empresas', [
            'conteos' => ['todas' => $total, 'vigente' => $conVigente, 'sin' => $total - $conVigente],
            'empresas' => $empresas,
            'tipos' => TipoOrganismo::cases(),
            'estados' => ConvenioEstado::cases(),
            'situaciones' => Empresa::SITUACIONES,
        ]);
    }

    /** Lista de empresas en PDF, agrupada por situación (respeta los filtros de la bandeja). */
    public function pdf(Request $request): Response
    {
        $situacion = $request->string('situacion')->toString();

        $empresas = $this->consulta($request)->get()
            ->map(fn (Empresa $e) => [$e, $e->situacion()])
            ->when(isset(Empresa::SITUACIONES[$situacion]), fn ($c) => $c->filter(fn ($par) => $par[1]['clave'] === $situacion))
            ->groupBy(fn ($par) => $par[1]['clave'])
            ->sortBy(fn ($grupo, $clave) => array_search($clave, array_keys(Empresa::SITUACIONES)));

        Auditoria::registrar('informe.exportado', 'Descargó la lista de empresas en PDF ('.$empresas->flatten(1)->count().' empresas)');

        return Pdf::loadView('admin.pdf.empresas', [
            'grupos' => $empresas,
            'filtro' => Empresa::SITUACIONES[$situacion][0] ?? null,
            'busqueda' => $request->string('q')->toString(),
        ])->setOption('isFontSubsettingEnabled', true)->setPaper('letter', 'landscape')
            ->download('empresas-'.($situacion ?: 'todas').'-'.now()->format('Y-m-d').'.pdf');
    }

    /** Filtros compartidos por la bandeja y el PDF. */
    private function consulta(Request $request)
    {
        return Empresa::with(['user', 'convenios'])
            ->withCount('convenios')
            ->when($request->filled('q'), fn ($q) => $q->where(fn ($w) => $w
                ->where('razon_social', 'like', '%'.$request->string('q').'%')
                ->orWhere('rfc', 'like', '%'.$request->string('q').'%')
                ->orWhere('contacto_nombre', 'like', '%'.$request->string('q').'%')))
            ->when($request->filled('tipo'), fn ($q) => $q->where('tipo_organismo', $request->string('tipo')))
            ->when($request->string('convenio')->toString() === 'vigente', fn ($q) => $q->whereHas('convenios', fn ($c) => $c->vigentes()))
            ->when($request->string('convenio')->toString() === 'sin', fn ($q) => $q->whereDoesntHave('convenios', fn ($c) => $c->vigentes()))
            ->orderBy('razon_social');
    }

    public function show(Empresa $empresa): View
    {
        $empresa->load(['user', 'convenios.dictamenes.carrera']);

        return view('admin.empresa-show', [
            'empresa' => $empresa,
            'colecciones' => $empresa->documentosAplicables(),
            'expedienteCompleto' => $empresa->expedienteCompleto(),
        ]);
    }

    public function update(Request $request, Empresa $empresa): RedirectResponse
    {
        $request->merge([
            'rfc' => Str::upper(trim((string) $request->input('rfc'))),
            'contacto_telefono' => Formatos::digitos($request->input('contacto_telefono')) ?: null,
        ]);

        $datos = $request->validate([
            'rfc' => ['required', 'string', 'regex:/^[A-ZÑ&]{3,4}\d{6}[A-Z\d]{3}$/u', Rule::unique('empresas', 'rfc')->ignore($empresa->id)],
            'razon_social' => ['required', 'string', 'max:255', new UnicoNormalizado('empresas', 'razon_social', 'Ya hay otra organización registrada con esa razón social.', $empresa->id)],
            'tipo_organismo' => ['required', Rule::enum(TipoOrganismo::class)],
            'domicilio_fiscal' => ['nullable', 'string', 'max:600'],
            'representante_legal' => ['nullable', 'string', 'max:255', 'regex:'.Formatos::NOMBRE_PERSONA],
            'contacto_nombre' => ['required', 'string', 'max:255', 'regex:'.Formatos::NOMBRE_PERSONA],
            'contacto_cargo' => ['nullable', 'string', 'max:255', 'regex:'.Formatos::TEXTO_LETRAS],
            'contacto_telefono' => ['nullable', 'regex:'.Formatos::TELEFONO],
            'descripcion' => ['nullable', 'string', 'max:3000'],
            'proyectos_principales' => ['nullable', 'string', 'max:3000'],
        ], Formatos::mensajes() + [
            'contacto_nombre.regex' => 'Escribe el nombre completo del contacto (nombre y apellido), solo con letras.',
        ]);

        $empresa->update($datos);

        return back()->with('status', 'Empresa actualizada.');
    }

    /** Activa o desactiva la cuenta de acceso de la empresa. */
    public function toggleActivo(Empresa $empresa): RedirectResponse
    {
        $user = $empresa->user;

        abort_if($user === null, 404);

        $user->update(['activo' => ! $user->activo]);

        return back()->with('status', $user->activo ? 'Cuenta activada.' : 'Cuenta desactivada.');
    }
}
