<?php

/*
 * This file is part of the Novo SGA project.
 *
 * (c) Rogerio Lino <rogeriolino@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Novosga\SettingsBundle\Controller;

use App\Service\SecurityService;
use Exception;
use Novosga\Entity\Contador;
use Novosga\Entity\Local;
use Novosga\Entity\Servico;
use Novosga\Entity\ServicoUnidade;
use Novosga\Entity\ServicoUsuario;
use Novosga\Entity\Usuario;
use Novosga\Http\Envelope;
use Novosga\Service\AtendimentoService;
use Novosga\Service\FilaService;
use Novosga\Service\UsuarioService;
use Novosga\Service\ServicoService;
use Novosga\SettingsBundle\Form\ImpressaoType;
use Novosga\SettingsBundle\Form\ServicoUnidadeType;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Translation\TranslatorInterface;
use function array_map;
use function array_filter;

/**
 * DefaultController
 *
 * Controlador do módulo de configuração da unidade
 *
 * @author Rogerio Lino <rogeriolino@gmail.com>
 */
class DefaultController extends AbstractController
{
    const DOMAIN = 'NovosgaSettingsBundle';

    /**
     * @param Request $request
     * @return Response
     *
     * @Route("/", name="novosga_settings_index", methods={"GET"})
     */
    public function index(
        Request $request,
        ServicoService $servicoService,
        SecurityService $securityService,
        UsuarioService $usuarioService,
        TranslatorInterface $translator
    ) {
        $em = $this->getDoctrine()->getManager();
        
        $usuario = $this->getUser();
        $unidade = $usuario->getLotacao()->getUnidade();
        
        // locais disponiveis
        $locais = $em
            ->getRepository(Local::class)
            ->findBy([], ['nome' => 'ASC']);

        // usuarios da unidade
        $usuarios = $em
            ->getRepository(Usuario::class)
            ->findByUnidade($unidade);
        
        $servicosUnidade = $servicoService->servicosUnidade($unidade);
        
        $usuariosArray = array_map(function (Usuario $usuario) use (
            $em,
            $unidade,
            $servicosUnidade,
            $usuarioService
        ) {
            $servicosUsuario = $em
                ->getRepository(ServicoUsuario::class)
                ->getAll($usuario, $unidade);
            
            $data  = $usuario->jsonSerialize();
            $data['servicos'] = [];
                    
            foreach ($servicosUnidade as $servicoUnidade) {
                foreach ($servicosUsuario as $servicoUsuario) {
                    $idA = $servicoUsuario->getServico()->getId();
                    $idB = $servicoUnidade->getServico()->getId();
                    
                    if ($idA === $idB) {
                        $data['servicos'][] = [
                            'id'    => $servicoUnidade->getServico()->getId(),
                            'sigla' => $servicoUnidade->getSigla(),
                            'nome'  => $servicoUnidade->getServico()->getNome(),
                            'peso'  => $servicoUsuario->getPeso(),
                        ];
                    }
                }
            }
            
            $tipoMeta                = $usuarioService->meta($usuario, UsuarioService::ATTR_ATENDIMENTO_TIPO);
            $data['tipoAtendimento'] = $tipoMeta ? $tipoMeta->getValue() : FilaService::TIPO_TODOS;
            
            $numeroMeta      = $usuarioService->meta($usuario, UsuarioService::ATTR_ATENDIMENTO_LOCAL);
            $data['numero']  = $numeroMeta ? (int) $numeroMeta->getValue() : null;
            
            return $data;
        }, $usuarios);
        
        $tiposAtendimento = $this->getTiposAtendimento($translator);
        
        $form          = $this->createForm(ServicoUnidadeType::class);
        $inlineForm    = $this->createForm(ServicoUnidadeType::class);
        $impressaoForm = $this->createForm(ImpressaoType::class, $unidade->getImpressao());

        return $this->render('@NovosgaSettings/default/index.html.twig', [
            'usuario'          => $usuario,
            'unidade'          => $unidade,
            'locais'           => $locais,
            'usuarios'         => $usuariosArray,
            'tiposAtendimento' => $tiposAtendimento,
            'form'             => $form->createView(),
            'inlineForm'       => $inlineForm->createView(),
            'impressaoForm'    => $impressaoForm->createView(),
            'wsSecret'         => $securityService->getWebsocketSecret(),
        ]);
    }
    
    /**
     * @param Request $request
     * @return Response
     *
     * @Route("/servicos", name="novosga_settings_servicos", methods={"GET"})
     */
    public function servicos(Request $request, ServicoService $servicoService)
    {
        $ids = array_filter(explode(',', $request->get('ids')), function ($i) {
            return $i > 0;
        });
        
        if (empty($ids)) {
            $ids = [0];
        }
        
        $servicos = $this
            ->getDoctrine()
            ->getManager()
            ->createQueryBuilder()
            ->select('e')
            ->from(Servico::class, 'e')
            ->where('e.mestre IS NULL')
            ->andWhere('e.deletedAt IS NULL')
            ->andWhere('e.id NOT IN (:ids)')
            ->orderBy('e.nome', 'ASC')
            ->setParameters([
                'ids' => $ids
            ])
            ->getQuery()
            ->getResult();
        
        $envelope = new Envelope();
        $envelope->setData($servicos);
        
        return $this->json($envelope);
    }
    
    /**
     * @param Request $request
     * @return Response
     *
     * @Route("/servicos_unidade", name="novosga_settings_servicos_unidade", methods={"GET"})
     */
    public function servicosUnidade(Request $request, ServicoService $servicoService)
    {
        $usuario  = $this->getUser();
        $unidade  = $usuario->getLotacao()->getUnidade();
        $servicos = $servicoService->servicosUnidade($unidade);
        
        $envelope = new Envelope();
        $envelope->setData($servicos);
        
        return $this->json($envelope);
    }
    
    /**
     * @param Request $request
     * @return Response
     *
     * @Route("/servicos_unidade", name="novosga_settings_add_servico_unidade", methods={"POST"})
     */
    public function addServico(Request $request, ServicoService $servicoService)
    {
        $json    = $request->getContent();
        $data    = json_decode($json, true);
        $ids     = $data['ids'] ?? [];
        $unidade = $this->getUser()->getLotacao()->getUnidade();
        $em      = $this->getDoctrine()->getManager();
        
        if (!is_array($ids)) {
            $ids = [];
        }
        
        // locais disponiveis
        $locais = $em
            ->getRepository(Local::class)
            ->findBy([], ['nome' => 'ASC']);
        
        if (empty($locais)) {
            throw new \Exception('Nenhum local disponível');
        }

        $count = count($servicoService->servicosUnidade($unidade));
        
        foreach ($ids as $id) {
            $servico = $em->find(Servico::class, $id);

            if ($servico) {
                $sigla = $this->novaSigla(++$count);

                $su = new ServicoUnidade();
                $su->setUnidade($unidade);
                $su->setServico($servico);
                $su->setIncremento(1);
                $su->setLocal($locais[0]);
                $su->setMensagem('');
                $su->setNumeroInicial(1);
                $su->setPeso(1);
                $su->setPrioridade(true);
                $su->setSigla($sigla);
                $su->setAtivo(false);
                
                $contador = $this
                    ->getDoctrine()
                    ->getManager()
                    ->getRepository(Contador::class)
                    ->findOneBy([
                        'unidade' => $unidade,
                        'servico' => $servico,
                    ]);
                
                if (!$contador) {
                    $contador = new Contador();
                    $contador->setServico($servico);
                    $contador->setUnidade($unidade);
                    $contador->setNumero($su->getNumeroInicial());
                    $em->persist($contador);
                } else {
                    $contador->setNumero($su->getNumeroInicial());
                    $em->merge($contador);
                }

                $em->persist($su);
                $em->flush();
            }
        }
        
        $envelope = new Envelope();
        
        return $this->json($envelope);
    }
    
    /**
     * @Route("/servicos_unidade/{id}", name="novosga_settings_remove_servico_unidade", methods={"DELETE"})
     */
    public function removeServicoUnidade(Request $request, Servico $servico, TranslatorInterface $translator)
    {
        $em       = $this->getDoctrine()->getManager();
        $unidade  = $this->getUser()->getLotacao()->getUnidade();
        $envelope = new Envelope();
        
        $su = $em
            ->getRepository(ServicoUnidade::class)
            ->get($unidade, $servico);

        if (!$su) {
            throw new Exception($translator->trans('error.invalid_service', [], self::DOMAIN));
        }

        if ($su->isAtivo()) {
            throw new Exception($translator->trans('error.cannot_remove_disabled_service', [], self::DOMAIN));
        }
        
        $em->transactional(function ($em) use ($su, $unidade, $servico) {
            $em->remove($su);
        
            $em
                ->createQueryBuilder()
                ->delete(Contador::class, 'e')
                ->where('e.unidade = :unidade AND e.servico = :servico')
                ->setParameters([
                    'unidade' => $unidade,
                    'servico' => $servico,
                ])
                ->getQuery()
                ->execute();

            $em
                ->createQueryBuilder()
                ->delete(ServicoUsuario::class, 'e')
                ->where('e.unidade = :unidade AND e.servico = :servico')
                ->setParameters([
                    'unidade' => $unidade,
                    'servico' => $servico,
                ])
                ->getQuery()
                ->execute();
        });
        
        return $this->json($envelope);
    }
    
    /**
     * @param Request $request
     * @return Response
     *
     * @Route("/servicos_unidade/{id}", name="novosga_settings_update_servicos_unidade", methods={"PUT"})
     */
    public function updateServico(Request $request, Servico $servico)
    {
        $json = $request->getContent();
        $data = json_decode($json, true);
        
        $em      = $this->getDoctrine()->getManager();
        $usuario = $this->getUser();
        $unidade = $usuario->getLotacao()->getUnidade();
        
        $su = $em
            ->getRepository(ServicoUnidade::class)
            ->get($unidade, $servico);
        
        $form = $this->createForm(ServicoUnidadeType::class, $su);
        $form->submit($data);
        
        $em->merge($su);
        $em->flush();
        
        $envelope = new Envelope();
        $envelope->setData($su);
        
        return $this->json($envelope);
    }
    
    /**
     * @param Request $request
     * @return Response
     *
     * @Route("/contadores", name="novosga_settings_contadores", methods={"GET"})
     */
    public function contadores(Request $request)
    {
        $em = $this->getDoctrine()->getManager();
        $usuario = $this->getUser();
        $unidade = $usuario->getLotacao()->getUnidade();
        
        $contadores = $em
            ->createQueryBuilder()
            ->select('e')
            ->from(Contador::class, 'e')
            ->join('e.servico', 's')
            ->join(ServicoUnidade::class, 'su', 'WITH', 'su.servico = s')
            ->where('e.unidade = :unidade')
            ->setParameter('unidade', $unidade)
            ->getQuery()
            ->getResult();
        
        $envelope = new Envelope();
        $envelope->setData($contadores);
        
        return $this->json($envelope);
    }

    /**
     * @param Request $request
     * @return Response
     *
     * @Route("/update_impressao", name="novosga_settings_update_impressao", methods={"POST"})
     */
    public function updateImpressao(Request $request)
    {
        $envelope = new Envelope();
        
        $em      = $this->getDoctrine()->getManager();
        $usuario = $this->getUser();
        $unidade = $usuario->getLotacao()->getUnidade();

        $data = json_decode($request->getContent(), true);

        $form = $this->createForm(ImpressaoType::class, $unidade->getImpressao());
        $form->submit($data);

        $em->merge($unidade);
        $em->flush();

        $envelope->setData($unidade);

        return $this->json($envelope);
    }

    /**
     * @param Request $request
     * @return Response
     *
     * @Route("/reiniciar/{id}", name="novosga_settings_reiniciar_contador", methods={"POST"})
     */
    public function reiniciarContador(Request $request, Servico $servico, TranslatorInterface $translator)
    {
        $envelope = new Envelope();
        
        $em = $this->getDoctrine()->getManager();

        $usuario = $this->getUser();
        $unidade = $usuario->getLotacao()->getUnidade();

        $su = $em
            ->getRepository(ServicoUnidade::class)
            ->get($unidade, $servico);

        if (!$su) {
            throw new Exception($translator->trans('error.invalid_service', [], self::DOMAIN));
        }

        $contador = $em
            ->getRepository(Contador::class)
            ->findOneBy([
                'unidade' => $unidade->getId(),
                'servico' => $servico->getId()
            ]);

        $contador->setNumero($su->getNumeroInicial());
        $em->merge($contador);
        $em->flush();

        $envelope->setData($contador);

        return $this->json($envelope);
    }

    /**
     * @param Request $request
     * @return Response
     *
     * @Route("/limpar", name="novosga_settings_limpar_dados", methods={"POST"})
     */
    public function limparDados(Request $request, AtendimentoService $atendimentoService)
    {
        $usuario = $this->getUser();
        $unidade = $usuario->getLotacao()->getUnidade();
        
        $atendimentoService->limparDados($unidade);
        
        $envelope = new Envelope();
        $envelope->setData(true);

        return $this->json($envelope);
    }

    /**
     * @param Request $request
     * @return Response
     *
     * @Route("/acumular_atendimentos", name="novosga_settings_acumular_atendimentos", methods={"POST"})
     */
    public function reiniciar(Request $request, AtendimentoService $atendimentoService)
    {
        $envelope = new Envelope();
        $usuario  = $this->getUser();
        $unidade  = $usuario->getLotacao()->getUnidade();

        $atendimentoService->acumularAtendimentos($unidade);

        return $this->json($envelope);
    }
    
    /**
     * @Route("/servico_usuario/{usuarioId}/{servicoId}", name="novosga_settings_add_servico_usuario", methods={"POST"})
     * @ParamConverter("usuario", options={"id" = "usuarioId"})
     * @ParamConverter("servico", options={"id" = "servicoId"})
     */
    public function addServicoUsuario(
        Request $request,
        Usuario $usuario,
        Servico $servico,
        TranslatorInterface $translator
    ) {
        $em       = $this->getDoctrine()->getManager();
        $unidade  = $this->getUser()->getLotacao()->getUnidade();
        $envelope = new Envelope();
        
        $su = $em
            ->getRepository(ServicoUnidade::class)
            ->get($unidade, $servico);

        if (!$su) {
            throw new Exception($translator->trans('error.invalid_service', [], self::DOMAIN));
        }

        $servicoUsuario = new ServicoUsuario();
        $servicoUsuario->setUsuario($usuario);
        $servicoUsuario->setServico($servico);
        $servicoUsuario->setUnidade($unidade);
        $servicoUsuario->setPeso(1);

        $em->persist($servicoUsuario);
        $em->flush();
        
        $envelope->setData($servicoUsuario);
        
        return $this->json($envelope);
    }
    
    /**
     * @Route(
     *   "/servico_usuario/{usuarioId}/{servicoId}",
     *   name="novosga_settings_remove_servico_usuario",
     *   methods={"DELETE"}
     * )
     * @ParamConverter("usuario", options={"id" = "usuarioId"})
     * @ParamConverter("servico", options={"id" = "servicoId"})
     */
    public function removeServicoUsuario(
        Request $request,
        Usuario $usuario,
        Servico $servico,
        TranslatorInterface $translator
    ) {
        $em       = $this->getDoctrine()->getManager();
        $unidade  = $this->getUser()->getLotacao()->getUnidade();
        $envelope = new Envelope();
        
        $su = $em
            ->getRepository(ServicoUnidade::class)
            ->get($unidade, $servico);

        if (!$su) {
            throw new Exception($translator->trans('error.invalid_service', [], self::DOMAIN));
        }

        $servicoUsuario = $em
            ->getRepository(ServicoUsuario::class)
            ->findOneBy([
                'usuario' => $usuario,
                'servico' => $servico,
                'unidade' => $unidade
            ]);

        $em->remove($servicoUsuario);
        $em->flush();
        
        return $this->json($envelope);
    }
    
    /**
     * @Route(
     *   "/servico_usuario/{usuarioId}/{servicoId}",
     *   name="novosga_settings_update_servico_usuario",
     *   methods={"PUT"}
     * )
     * @ParamConverter("usuario", options={"id" = "usuarioId"})
     * @ParamConverter("servico", options={"id" = "servicoId"})
     */
    public function updateServicoUsuario(
        Request $request,
        Usuario $usuario,
        Servico $servico,
        TranslatorInterface $translator
    ) {
        $em       = $this->getDoctrine()->getManager();
        $unidade  = $this->getUser()->getLotacao()->getUnidade();
        $envelope = new Envelope();
        
        $su = $em
            ->getRepository(ServicoUnidade::class)
            ->get($unidade, $servico);

        if (!$su) {
            throw new Exception($translator->trans('error.invalid_service', [], self::DOMAIN));
        }
        
        $json = json_decode($request->getContent());
        
        $servicoUsuario = $em
            ->getRepository(ServicoUsuario::class)
            ->findOneBy([
                'usuario' => $usuario,
                'servico' => $servico,
                'unidade' => $unidade
            ]);
        
        if (isset($json->peso) && $json->peso > 0) {
            $servicoUsuario->setPeso($json->peso);
            $em->merge($servicoUsuario);
            $em->flush();
        }
        
        $envelope->setData($servicoUsuario);

        return $this->json($envelope);
    }
    
    /**
     * @Route("/usuario/{id}", name="novosga_settings_update_usuario", methods={"PUT"})
     */
    public function updateUsuario(
        Request $request,
        Usuario $usuario,
        UsuarioService $usuarioService,
        TranslatorInterface $translator
    ) {
        $json     = json_decode($request->getContent());
        $em       = $this->getDoctrine()->getManager();
        $envelope = new Envelope();
        
        $tipos = array_keys($this->getTiposAtendimento($translator));
        
        if (isset($json->tipoAtendimento) && in_array($json->tipoAtendimento, $tipos)) {
            $usuarioService->meta($usuario, UsuarioService::ATTR_ATENDIMENTO_TIPO, $json->tipoAtendimento);
        }
        
        if (isset($json->numero) && (($numero = (int) $json->numero)) > 0) {
            $usuarioService->meta($usuario, UsuarioService::ATTR_ATENDIMENTO_LOCAL, $numero);
        }
        
        return $this->json($envelope);
    }
    
    private function getTiposAtendimento(TranslatorInterface $translator)
    {
        return [
            FilaService::TIPO_TODOS        => $translator->trans('label.all', [], self::DOMAIN),
            FilaService::TIPO_NORMAL       => $translator->trans('label.normal', [], self::DOMAIN),
            FilaService::TIPO_PRIORIDADE   => $translator->trans('label.priority', [], self::DOMAIN),
            FilaService::TIPO_AGENDAMENTO  => $translator->trans('label.schedule', [], self::DOMAIN),
        ];
    }

    private function novaSigla($c)
    {
        $c = intval($c);

        if ($c <= 0) {
            return '';
        }
    
        $letter = '';
                 
        while ($c != 0) {
           $p      = ($c - 1) % 26;
           $c      = intval(($c - $p) / 26);
           $letter = chr(65 + $p) . $letter;
        }
        
        return $letter;
    }   
}
