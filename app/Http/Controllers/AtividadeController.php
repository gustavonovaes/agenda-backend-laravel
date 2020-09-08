<?php

namespace App\Http\Controllers;

use App\Atividade;
use App\Http\Requests\ConcluiAtividadeRequest;
use App\Http\Requests\RemoveAtividadeRequest;
use App\Http\Requests\StoreAtividadeRequest;
use App\Http\Requests\UpdateAtividadeRequest;
use App\Http\Resources\AtividadeResource;
use Carbon\Carbon;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Redis;

class AtividadeController extends Controller
{
    /**
     * Lista todas as atividades.
     *
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function index(): AnonymousResourceCollection
    {
        $atividades = Atividade::orderBy('created_at', 'desc')->paginate(10);

        return AtividadeResource::collection($atividades);
    }

    /**
     * Amazena uma atividade.
     *
     * @param  App\Http\Requests\StoreAtividadeRequest  $request
     * @return \Illuminate\Http\Response
     */
    public function store(StoreAtividadeRequest $request): Response
    {
        // TODO: Validar intersecção de datas entre atividades

        $dataInicio = Carbon::parse($request->data_inicio)->format('Y-m-d');
        $dataPrazo = $request->data_prazo ? Carbon::parse($request->data_prazo)->format('Y-m-d') : null;

        $atividade = Atividade::create([
            'titulo' => $request->titulo,
            'descricao' => $request->descricao,
            'tipo' => $request->tipo,
            'user_id' => $request->user_id,
            'data_inicio' => $dataInicio,
            'data_prazo' => $dataPrazo,
            'status' => $request->status,
        ]);

        return \response(
            new AtividadeResource($atividade),
            Response::HTTP_CREATED
        );
    }

    /**
     * Atualiza uma atividade.
     *
     * @param  App\Http\Requests\UpdateAtividadeRequest  $request
     * @param  \App\Atividade  $atividade
     * @return \Illuminate\Http\Response
     */
    public function update(UpdateAtividadeRequest $request, Atividade $atividade): Response
    {
        // TODO: Validar intersecção de datas entre atividades

        $dataInicio = Carbon::parse($request->data_inicio)->format('Y-m-d');
        $dataPrazo = $request->data_prazo ? Carbon::parse($request->data_prazo)->format('Y-m-d') : null;

        $dataConclusao = $request->status === Atividade::STATUS_CONCLUIDA
            ? \now()
            : null;

        $atividade->update([
            'titulo' => $request->titulo,
            'descricao' => $request->descricao,
            'tipo' => $request->tipo,
            'user_id' => $request->user_id,
            'data_inicio' => $dataInicio,
            'data_prazo' => $dataPrazo,
            'status' => $request->status,
            'data_conclusao' => $dataConclusao,
        ]);

        return \response(new AtividadeResource($atividade), Response::HTTP_OK);
    }

    /**
     * Remove uma atividade.
     *
     * @param  App\Http\Requests\RemoveAtividadeRequest $request
     * @param  \App\Atividade  $atividade
     * @return \Illuminate\Http\Response
     */
    public function destroy(RemoveAtividadeRequest $request, Atividade $atividade): Response
    {
        $atividade->delete();

        return \response(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * Marca atividade como concluída.
     *
     * @param  App\Http\Requests\ConcluiAtividadeRequest $request
     * @param  \App\Atividade  $atividade
     * @return \Illuminate\Http\Response
     */
    public function conclui(ConcluiAtividadeRequest $request, Atividade $atividade): Response
    {
        $atividade->update([
            'data_conclusao' => \now(),
            'status' => Atividade::STATUS_CONCLUIDA,
        ]);

        return \response(new AtividadeResource($atividade), Response::HTTP_OK);
    }

    /**
     * Retorna tipos usados nas atividades
     *
     * @return \Illuminate\Http\Response
     */
    public function getTipos(): Response
    {
        $redisKey = 'atividades.tipos';
        $cacheTimeout = 60;

        if (Redis::exists($redisKey)) {
            $tiposJson = Redis::get($redisKey);
            $tipos = \json_decode($tiposJson);
        } else {
            $tipos = Atividade::all('tipo')
                ->map(fn ($atividade) => $atividade->tipo)
                ->unique()
                ->toArray();

            $tiposJson = \json_encode($tipos);

            Redis::set($redisKey, $tiposJson, 'EX', $cacheTimeout);
        }

        return \response($tipos, Response::HTTP_OK);
    }
}