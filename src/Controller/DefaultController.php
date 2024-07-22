<?php

declare(strict_types=1);

/*
 * This file is part of the Novo SGA project.
 *
 * (c) Rogerio Lino <rogeriolino@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Novosga\SettingsBundle\Controller;

use Exception;
use Doctrine\ORM\EntityManagerInterface;
use Novosga\Entity\UsuarioInterface;
use Novosga\Http\Envelope;
use Novosga\Repository\ContadorRepositoryInterface;
use Novosga\Repository\LocalRepositoryInterface;
use Novosga\Repository\ServicoRepositoryInterface;
use Novosga\Repository\ServicoUnidadeRepositoryInterface;
use Novosga\Repository\ServicoUsuarioRepositoryInterface;
use Novosga\Repository\UsuarioRepositoryInterface;
use Novosga\Service\AtendimentoServiceInterface;
use Novosga\Service\FilaServiceInterface;
use Novosga\Service\ServicoServiceInterface;
use Novosga\Service\UnidadeServiceInterface;
use Novosga\Service\UsuarioServiceInterface;
use Novosga\SettingsBundle\Form\ImpressaoType;
use Novosga\SettingsBundle\Form\ServicoUnidadeType;
use Novosga\SettingsBundle\NovosgaSettingsBundle;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\Translation\TranslatorInterface;
use function array_map;
use function array_filter;

/**
 * DefaultController
 *
 * Controlador do módulo de configuração da unidade
 *
 * @author Rogerio Lino <rogeriolino@gmail.com>
 */
#[Route("/", name: "novosga_settings_")]
class DefaultController extends AbstractController
{
    #[Route("/", name: "index", methods: ['GET'])]
    public function index(
        ServicoServiceInterface $servicoService,
        UsuarioServiceInterface $usuarioService,
        UsuarioRepositoryInterface $usuarioRepository,
        LocalRepositoryInterface $localRepository,
        ServicoUsuarioRepositoryInterface $servicoUsuarioRepository,
        TranslatorInterface $translator,
    ): Response {
        /** @var UsuarioInterface */
        $usuario = $this->getUser();
        $unidade = $usuario->getLotacao()->getUnidade();
        // locais disponiveis
        $locais = $localRepository->findBy([], ['nome' => 'ASC']);
        // usuarios da unidade
        $todosUsuarios = $usuarioRepository->findByUnidade($unidade);

        $usuarios = array_values(array_filter(
            $todosUsuarios,
            fn (UsuarioInterface $usuario) => $usuario->isAtivo(),
        ));

        $servicosUnidade = $servicoService->servicosUnidade($unidade);

        $usuariosArray = array_map(function (UsuarioInterface $usuario) use (
            $unidade,
            $servicosUnidade,
            $usuarioService,
            $servicoUsuarioRepository,
        ) {
            $servicosUsuario = $servicoUsuarioRepository->getAll($usuario, $unidade);

            $data  = $usuario->jsonSerialize();
            $data['servicos'] = [];

            foreach ($servicosUnidade as $servicoUnidade) {
                foreach ($servicosUsuario as $servicoUsuario) {
                    $idA = $servicoUsuario->getServico()->getId();
                    $idB = $servicoUnidade->getServico()->getId();

                    if ($idA === $idB) {
                        $data['servicos'][] = [
                            'id' => $servicoUnidade->getServico()->getId(),
                            'sigla' => $servicoUnidade->getSigla(),
                            'nome' => $servicoUnidade->getServico()->getNome(),
                            'peso' => $servicoUsuario->getPeso(),
                        ];
                    }
                }
            }

            $tipoMeta = $usuarioService->meta($usuario, UsuarioServiceInterface::ATTR_ATENDIMENTO_TIPO);
            $data['tipoAtendimento'] = $tipoMeta ? $tipoMeta->getValue() : FilaServiceInterface::TIPO_TODOS;

            $localMeta = $usuarioService->meta($usuario, UsuarioServiceInterface::ATTR_ATENDIMENTO_LOCAL);
            $data['local'] = $localMeta ? (int) $localMeta->getValue() : null;

            $numeroMeta = $usuarioService->meta($usuario, UsuarioServiceInterface::ATTR_ATENDIMENTO_NUM_LOCAL);
            $data['numero'] = $numeroMeta ? (int) $numeroMeta->getValue() : null;

            return $data;
        }, $usuarios);
        
        $tiposAtendimento = $this->getTiposAtendimento($translator);

        $form = $this->createForm(ServicoUnidadeType::class);
        $inlineForm = $this->createForm(ServicoUnidadeType::class);
        $impressaoForm = $this->createForm(ImpressaoType::class, $unidade->getImpressao());

        return $this->render('@NovosgaSettings/default/index.html.twig', [
            'usuario' => $usuario,
            'unidade' => $unidade,
            'locais' => $locais,
            'usuarios' => $usuariosArray,
            'tiposAtendimento' => $tiposAtendimento,
            'form' => $form,
            'inlineForm' => $inlineForm,
            'impressaoForm' => $impressaoForm,
        ]);
    }

    #[Route("/servicos", name: "servicos", methods: ['GET'])]
    public function servicos(
        Request $request,
        ServicoRepositoryInterface $servicoRepository,
    ): Response {
        $ids = array_filter(explode(',', $request->get('ids')), function ($i) {
            return $i > 0;
        });

        if (empty($ids)) {
            $ids = [0];
        }

        $servicos = $servicoRepository
            ->createQueryBuilder('e')
            ->where('e.mestre IS NULL')
            ->andWhere('e.deletedAt IS NULL')
            ->andWhere('e.id NOT IN (:ids)')
            ->orderBy('e.nome', 'ASC')
            ->setParameter('ids', $ids)
            ->getQuery()
            ->getResult();

        $envelope = new Envelope();
        $envelope->setData($servicos);

        return $this->json($envelope);
    }

    #[Route("/servicos_unidade", name: "servicos_unidade", methods: ['GET'])]
    public function servicosUnidade(ServicoServiceInterface $servicoService): Response
    {
        /** @var UsuarioInterface */
        $usuario = $this->getUser();
        $unidade = $usuario->getLotacao()->getUnidade();
        $servicos = $servicoService->servicosUnidade($unidade);

        $envelope = new Envelope();
        $envelope->setData($servicos);

        return $this->json($envelope);
    }

    #[Route("/servicos_unidade", name: "add_servico_unidade", methods: ['POST'])]
    public function addServico(
        Request $request,
        ServicoRepositoryInterface $servicoRepository,
        ServicoServiceInterface $servicoService,
        UnidadeServiceInterface $unidadeService,
    ): Response {
        $json = $request->getContent();
        $data = json_decode($json, true);
        $ids = $data['ids'] ?? [];
        /** @var UsuarioInterface */
        $usuario = $this->getUser();
        $unidade = $usuario->getLotacao()->getUnidade();

        if (!is_array($ids)) {
            $ids = [];
        }

        $count = count($servicoService->servicosUnidade($unidade));
        foreach ($ids as $id) {
            $servico = $servicoRepository->find($id);
            if ($servico) {
                $sigla = $servicoService->gerarSigla(++$count);
                $unidadeService->addServicoUnidade($servico, $unidade, $sigla);
            }
        }

        $envelope = new Envelope();

        return $this->json($envelope);
    }

    #[Route("/servicos_unidade/{id}", name: "remove_servico_unidade", methods: ['DELETE'])]
    public function removeServicoUnidade(
        EntityManagerInterface $em,
        ContadorRepositoryInterface $contadorRepository,
        ServicoUsuarioRepositoryInterface $servicoUsuarioRepository,
        ServicoUnidadeRepositoryInterface $servicoUnidadeRepository,
        ServicoServiceInterface $servicoService,
        TranslatorInterface $translator,
        int $id,
    ): Response {
        $servico = $servicoService->getById($id);
        if (!$servico) {
            throw $this->createNotFoundException();
        }

        /** @var UsuarioInterface */
        $usuario = $this->getUser();
        $unidade = $usuario->getLotacao()->getUnidade();
        $envelope = new Envelope();

        $su = $servicoUnidadeRepository->get($unidade, $servico);
        if (!$su) {
            throw new Exception($translator->trans('error.invalid_service', [], NovosgaSettingsBundle::getDomain()));
        }

        if ($su->isAtivo()) {
            throw new Exception($translator->trans('error.cannot_remove_disabled_service', [], NovosgaSettingsBundle::getDomain()));
        }

        $em->beginTransaction();
        $em->remove($su);
        $contadorRepository
            ->createQueryBuilder('e')
            ->delete()
            ->where('e.unidade = :unidade AND e.servico = :servico')
            ->setParameter('unidade', $unidade)
            ->setParameter('servico', $servico)
            ->getQuery()
            ->execute();
        $servicoUsuarioRepository
            ->createQueryBuilder('e')
            ->delete()
            ->where('e.unidade = :unidade AND e.servico = :servico')
            ->setParameter('unidade', $unidade)
            ->setParameter('servico', $servico)
            ->getQuery()
            ->execute();
        $em->commit();
        $em->flush();

        return $this->json($envelope);
    }

    #[Route("/servicos_unidade/{id}", name: "update_servicos_unidade", methods: ['PUT'])]
    public function updateServico(
        Request $request,
        EntityManagerInterface $em,
        ServicoUnidadeRepositoryInterface $servicoUnidadeRepository,
        ServicoServiceInterface $servicoService,
        int $id,
    ): Response {
        $servico = $servicoService->getById($id);
        if (!$servico) {
            throw $this->createNotFoundException();
        }

        $json = $request->getContent();
        $data = json_decode($json, true);

        /** @var UsuarioInterface */
        $usuario = $this->getUser();
        $unidade = $usuario->getLotacao()->getUnidade();

        $su = $servicoUnidadeRepository->get($unidade, $servico);
        $form = $this
            ->createForm(ServicoUnidadeType::class, $su)
            ->submit($data);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($su);
            $em->flush();
        }

        $envelope = new Envelope();
        $envelope->setData($su);

        return $this->json($envelope);
    }

    #[Route("/contadores", name: "contadores", methods: ['GET'])]
    public function contadores(ContadorRepositoryInterface $contadorRepository): Response
    {
        /** @var UsuarioInterface */
        $usuario = $this->getUser();
        $unidade = $usuario->getLotacao()->getUnidade();

        $contadores = $contadorRepository
            ->createQueryBuilder('e')
            ->join('e.servico', 's')
            ->where('e.unidade = :unidade')
            ->setParameter('unidade', $unidade)
            ->getQuery()
            ->getResult();

        $envelope = new Envelope();
        $envelope->setData($contadores);

        return $this->json($envelope);
    }

    #[Route("/update_impressao", name: "update_impressao", methods: ['POST'])]
    public function updateImpressao(Request $request, EntityManagerInterface $em): Response
    {
        $envelope = new Envelope();

        /** @var UsuarioInterface */
        $usuario = $this->getUser();
        $unidade = $usuario->getLotacao()->getUnidade();

        $data = json_decode($request->getContent(), true);

        $form = $this
            ->createForm(ImpressaoType::class, $unidade->getImpressao())
            ->submit($data);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($unidade);
            $em->flush();
        }

        $envelope->setData($unidade);

        return $this->json($envelope);
    }

    #[Route("/reiniciar/{id}", name: "reiniciar_contador", methods: ['POST'])]
    public function reiniciarContador(
        EntityManagerInterface $em,
        ContadorRepositoryInterface $contadorRepository,
        ServicoServiceInterface $servicoService,
        ServicoUnidadeRepositoryInterface $servicoUnidadeRepository,
        TranslatorInterface $translator,
        int $id,
    ): Response {
        $servico = $servicoService->getById($id);
        if (!$servico) {
            throw $this->createNotFoundException();
        }

        $envelope = new Envelope();

        /** @var UsuarioInterface */
        $usuario = $this->getUser();
        $unidade = $usuario->getLotacao()->getUnidade();

        $su = $servicoUnidadeRepository->get($unidade, $servico);
        if (!$su) {
            throw new Exception($translator->trans('error.invalid_service', [], NovosgaSettingsBundle::getDomain()));
        }

        $contador = $contadorRepository->findOneBy([
            'unidade' => $unidade->getId(),
            'servico' => $servico->getId()
        ]);

        $contador->setNumero($su->getNumeroInicial());
        $em->persist($contador);
        $em->flush();

        $envelope->setData($contador);

        return $this->json($envelope);
    }

    #[Route("/limpar", name: "limpar_dados", methods: ['POST'])]
    public function limparDados(AtendimentoServiceInterface $atendimentoService): Response
    {
        /** @var UsuarioInterface */
        $usuario = $this->getUser();
        $unidade = $usuario->getLotacao()->getUnidade();

        $atendimentoService->limparDados($unidade);

        $envelope = new Envelope();
        $envelope->setData(true);

        return $this->json($envelope);
    }

    #[Route("/acumular_atendimentos", name: "acumular_atendimentos", methods: ['POST'])]
    public function reiniciar(AtendimentoServiceInterface $atendimentoService): Response
    {
        $envelope = new Envelope();
        /** @var UsuarioInterface */
        $usuario = $this->getUser();
        $unidade = $usuario->getLotacao()->getUnidade();

        $atendimentoService->acumularAtendimentos($unidade);

        return $this->json($envelope);
    }
    
    #[Route("/servico_usuario/{usuarioId}/{servicoId}", name: "add_servico_usuario", methods: ["POST"])]
    public function addServicoUsuario(
        UsuarioServiceInterface $usuarioService,
        ServicoServiceInterface $servicoService,
        ServicoUnidadeRepositoryInterface $servicoUnidadeRepository,
        TranslatorInterface $translator,
        int $usuarioId,
        int $servicoId,
    ) {
        $usuario = $usuarioService->getById($usuarioId);
        $servico = $servicoService->getById($servicoId);
        if (!$usuario || !$servico) {
            throw $this->createNotFoundException();
        }

        /** @var UsuarioInterface */
        $usuarioAtual = $this->getUser();
        $unidade = $usuarioAtual->getLotacao()->getUnidade();
        $envelope = new Envelope();

        $su = $servicoUnidadeRepository->get($unidade, $servicoId);
        if (!$su) {
            throw new Exception($translator->trans('error.invalid_service', [], NovosgaSettingsBundle::getDomain()));
        }

        $servicoUsuario = $usuarioService->addServicoUsuario($usuario, $servico, $unidade);
        $envelope->setData($servicoUsuario);

        return $this->json($envelope);
    }
    
    #[Route(
       "/servico_usuario/{usuarioId}/{servicoId}",
       name: "remove_servico_usuario",
       methods: ["DELETE"]
    )]
    public function removeServicoUsuario(
        UsuarioServiceInterface $usuarioService,
        ServicoServiceInterface $servicoService,
        ServicoUnidadeRepositoryInterface $servicoUnidadeRepository,
        TranslatorInterface $translator,
        int $usuarioId,
        int $servicoId,
    ) {
        $usuario = $usuarioService->getById($usuarioId);
        $servico = $servicoService->getById($servicoId);
        if (!$usuario || !$servico) {
            throw $this->createNotFoundException();
        }

        /** @var UsuarioInterface */
        $usuarioAtual = $this->getUser();
        $unidade = $usuarioAtual->getLotacao()->getUnidade();
        $envelope = new Envelope();

        $su = $servicoUnidadeRepository->get($unidade, $servicoId);
        if (!$su) {
            throw new Exception($translator->trans('error.invalid_service', [], NovosgaSettingsBundle::getDomain()));
        }

        $usuarioService->removeServicoUsuario($usuario, $servico, $unidade);

        return $this->json($envelope);
    }
    
    #[Route(
      "/servico_usuario/{usuarioId}/{servicoId}",
      name: "update_servico_usuario",
      methods: ["PUT"]
    )]
    public function updateServicoUsuario(
        Request $request,
        UsuarioServiceInterface $usuarioService,
        ServicoServiceInterface $servicoService,
        ServicoUnidadeRepositoryInterface $servicoUnidadeRepository,
        TranslatorInterface $translator,
        int $usuarioId,
        int $servicoId,
    ) {
        $usuario = $usuarioService->getById($usuarioId);
        $servico = $servicoService->getById($servicoId);
        if (!$usuario || !$servico) {
            throw $this->createNotFoundException();
        }

        /** @var UsuarioInterface */
        $usuario = $this->getUser();
        $unidade = $usuario->getLotacao()->getUnidade();
        $envelope = new Envelope();

        $su = $servicoUnidadeRepository->get($unidade, $servicoId);
        if (!$su) {
            throw new Exception($translator->trans('error.invalid_service', [], NovosgaSettingsBundle::getDomain()));
        }

        $json = json_decode($request->getContent());

        if (isset($json->peso) && $json->peso > 0) {
            $servicoUsuario = $usuarioService->updateServicoUsuario($usuario, $servico, $unidade, (int) $json->peso);
            $envelope->setData($servicoUsuario);
        }

        return $this->json($envelope);
    }

    #[Route("/usuario/{id}", name: "update_usuario", methods: ['PUT'])]
    public function updateUsuario(
        Request $request,
        UsuarioServiceInterface $usuarioService,
        int $id,
    ) {
        $usuario = $usuarioService->getById($id);
        if (!$usuario) {
            throw $this->createNotFoundException();
        }

        $json = json_decode($request->getContent());
        $envelope = new Envelope();

        $tipoAtendimento = isset($json->tipoAtendimento) ? $json->tipoAtendimento : null;
        $local = isset($json->local) ? (int) $json->local : null;
        $numero = isset($json->numero) ? (int) $json->numero : null;

        $usuarioService->updateAtendente($usuario, $tipoAtendimento, $local, $numero);

        return $this->json($envelope);
    }

    private function getTiposAtendimento(TranslatorInterface $translator): array
    {
        return [
            FilaServiceInterface::TIPO_TODOS => $translator->trans('label.all', [], NovosgaSettingsBundle::getDomain()),
            FilaServiceInterface::TIPO_NORMAL => $translator->trans('label.normal', [], NovosgaSettingsBundle::getDomain()),
            FilaServiceInterface::TIPO_PRIORIDADE => $translator->trans('label.priority', [], NovosgaSettingsBundle::getDomain()),
            FilaServiceInterface::TIPO_AGENDAMENTO => $translator->trans('label.schedule', [], NovosgaSettingsBundle::getDomain()),
        ];
    }  
}
