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
use Novosga\Service\ServicoService;
use Novosga\SettingsBundle\Form\ImpressaoType;
use Novosga\SettingsBundle\Form\ServicoUnidadeType;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;

/**
 * DefaultController
 *
 * Controlador do módulo de configuração da unidade
 *
 * @author Rogerio Lino <rogeriolino@gmail.com>
 */
class DefaultController extends Controller
{
    const DEFAULT_SIGLA = 'A';

    /**
     * @param Request $request
     * @return Response
     *
     * @Route("/", name="novosga_settings_index")
     */
    public function indexAction(Request $request, ServicoService $servicoService, SecurityService $securityService)
    {
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
        
        $usuariosArray = array_map(function (Usuario $usuario) use ($em, $unidade, $servicosUnidade) {
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
            
            return $data;
        }, $usuarios);

        $form          = $this->createForm(ServicoUnidadeType::class);
        $inlineForm    = $this->createForm(ServicoUnidadeType::class);
        $impressaoForm = $this->createForm(ImpressaoType::class, $unidade->getImpressao());

        return $this->render('@NovosgaSettings/default/index.html.twig', [
            'usuario'       => $usuario,
            'unidade'       => $unidade,
            'locais'        => $locais,
            'usuarios'      => $usuariosArray,
            'form'          => $form->createView(),
            'inlineForm'    => $inlineForm->createView(),
            'impressaoForm' => $impressaoForm->createView(),
            'wsSecret'      => $securityService->getWebsocketSecret(),
        ]);
    }
    
    /**
     * @param Request $request
     * @return Response
     *
     * @Route("/servicos", name="novosga_settings_servicos")
     * @Method("GET")
     */
    public function servicosAction(Request $request, ServicoService $servicoService)
    {
        $ids = explode(',', $request->get('ids'));
        
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
     * @Route("/servicos_unidade", name="novosga_settings_servicos_unidade")
     * @Method("GET")
     */
    public function servicosUnidadeAction(Request $request, ServicoService $servicoService)
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
     * @Route("/servicos_unidade", name="novosga_settings_add_servico_unidade")
     * @Method("POST")
     */
    public function addServicoAction(Request $request, ServicoService $servicoService)
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
        
        foreach ($ids as $id) {
            $servico = $em->find(Servico::class, $id);

            if ($servico) {
                $su = new ServicoUnidade();
                $su->setUnidade($unidade);
                $su->setServico($servico);
                $su->setIncremento(1);
                $su->setLocal($locais[0]);
                $su->setMensagem('');
                $su->setNumeroInicial(1);
                $su->setPeso(1);
                $su->setPrioridade(true);
                $su->setSigla(self::DEFAULT_SIGLA);
                $su->setAtivo(false);

                $em->persist($su);
                $em->flush();
            }
        }
        
        $envelope = new Envelope();
        
        return $this->json($envelope);
    }
    
    /**
     * @Route("/servicos_unidade/{id}", name="novosga_settings_remove_servico_unidade")
     * @Method("DELETE")
     */
    public function removeServicoUnidadeAction(Request $request, Servico $servico)
    {
        $unidade  = $this->getUser()->getLotacao()->getUnidade();
        $envelope = new Envelope();
        
        $su = $this
            ->getDoctrine()
            ->getManager()
            ->getRepository(ServicoUnidade::class)
            ->get($unidade, $servico);

        if (!$su) {
            throw new Exception(_('Serviço inválido'));
        }

        if ($su->isAtivo()) {
            throw new Exception(_('Não pode remover um serviço ativo'));
        }

        $em = $this->getDoctrine()->getManager();
        $em->remove($su);
        $em->flush();
        
        return $this->json($envelope);
    }
    
    /**
     * @param Request $request
     * @return Response
     *
     * @Route("/servicos_unidade/{id}", name="novosga_settings_update_servicos_unidade")
     * @Method("PUT")
     */
    public function updateServicoAction(Request $request, Servico $servico)
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
     * @Route("/contadores", name="novosga_settings_contadores")
     * @Method("GET")
     */
    public function contadoresAction(Request $request)
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
     * @Route("/update_impressao", name="novosga_settings_update_impressao")
     * @Method("POST")
     */
    public function updateImpressaoAction(Request $request)
    {
        $envelope = new Envelope();
        
        $em = $this->getDoctrine()->getManager();
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
     * @Route("/reiniciar/{id}", name="novosga_settings_reiniciar_contador")
     * @ Method("POST")
     */
    public function reiniciarContadorAction(Request $request, Servico $servico)
    {
        $envelope = new Envelope();
        
        $em = $this->getDoctrine()->getManager();

        $usuario = $this->getUser();
        $unidade = $usuario->getLotacao()->getUnidade();

        $su = $em
            ->getRepository(ServicoUnidade::class)
            ->get($unidade, $servico);

        if (!$su) {
            throw new Exception(_('Serviço inválido'));
        }

        $contador = $em->getRepository(Contador::class)
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
     * @Route("/limpar", name="novosga_settings_limpar_dados")
     * @ Method("POST")
     */
    public function limparDadosAction(Request $request, AtendimentoService $atendimentoService)
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
     * @Route("/acumular_atendimentos", name="novosga_settings_acumular_atendimentos")
     * @Method("POST")
     */
    public function reiniciarAction(Request $request, AtendimentoService $atendimentoService)
    {
        $envelope = new Envelope();
        
        $usuario = $this->getUser();
        $unidade = $usuario->getLotacao()->getUnidade();

        $atendimentoService->acumularAtendimentos($unidade);

        return $this->json($envelope);
    }
    
    /**
     * @Route("/servico_usuario/{usuarioId}/{servicoId}", name="novosga_settings_add_servico_usuario")
     * @ParamConverter("usuario", options={"id" = "usuarioId"})
     * @ParamConverter("servico", options={"id" = "servicoId"})
     * @Method("POST")
     */
    public function addServicoUsuarioAction(Request $request, Usuario $usuario, Servico $servico)
    {
        $em = $this->getDoctrine()->getManager();
        $unidade = $this->getUser()->getLotacao()->getUnidade();
        $envelope = new Envelope();
        
        $su = $em
            ->getRepository(ServicoUnidade::class)
            ->get($unidade, $servico);

        if (!$su) {
            throw new Exception(_('Serviço inválido'));
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
     * @Route("/servico_usuario/{usuarioId}/{servicoId}", name="novosga_settings_remove_servico_usuario")
     * @ParamConverter("usuario", options={"id" = "usuarioId"})
     * @ParamConverter("servico", options={"id" = "servicoId"})
     * @Method("DELETE")
     */
    public function removeServicoUsuarioAction(Request $request, Usuario $usuario, Servico $servico)
    {
        $em = $this->getDoctrine()->getManager();
        $unidade = $this->getUser()->getLotacao()->getUnidade();
        $envelope = new Envelope();
        
        $su = $em
            ->getRepository(ServicoUnidade::class)
            ->get($unidade, $servico);

        if (!$su) {
            throw new Exception(_('Serviço inválido'));
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
     * @Route("/servico_usuario/{usuarioId}/{servicoId}", name="novosga_settings_update_servico_usuario")
     * @ParamConverter("usuario", options={"id" = "usuarioId"})
     * @ParamConverter("servico", options={"id" = "servicoId"})
     * @Method("PUT")
     */
    public function updateServicoUsuarioAction(Request $request, Usuario $usuario, Servico $servico)
    {
        $em = $this->getDoctrine()->getManager();
        $unidade = $this->getUser()->getLotacao()->getUnidade();
        $envelope = new Envelope();
        
        $su = $em
            ->getRepository(ServicoUnidade::class)
            ->get($unidade, $servico);

        if (!$su) {
            throw new Exception(_('Serviço inválido'));
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
}
