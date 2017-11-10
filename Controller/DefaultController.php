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

use Exception;
use Novosga\Entity\Contador;
use Novosga\Entity\Local;
use Novosga\Entity\Lotacao;
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
    public function indexAction(Request $request, ServicoService $servicoService)
    {
        $em = $this->getDoctrine()->getManager();
        
        $usuario = $this->getUser();
        $unidade = $usuario->getLotacao()->getUnidade();
        
        // locais disponiveis
        $locais = $em
                    ->getRepository(Local::class)
                    ->findBy([], ['nome' => 'ASC']);

        // usuarios da unidade
        $lotacoes = $em
                    ->getRepository(Lotacao::class)
                    ->getLotacoesUnidade($unidade);
        
        $servicosUnidade = $servicoService->servicosUnidade($unidade);
        
        $usuarios = array_map(function (Lotacao $lotacao) use ($servicoService, $unidade, $servicosUnidade) {
            $usuario = $lotacao->getUsuario();
            $servicosUsuario = $servicoService->servicosUsuario($unidade, $usuario);
            
            $data  = $usuario->jsonSerialize();
            $data['servicos'] = [];
                    
            foreach ($servicosUnidade as $servicoUnidade) {
                foreach ($servicosUsuario as $servicoUsuario) {
                    $contains = $servicoUsuario->getServico()->getId() === $servicoUnidade->getServico()->getId();
                    if ($contains) {
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
        }, $lotacoes);

        if (count($locais)) {
            $local = $locais[0];
            $servicoService->updateUnidade($unidade, $local, self::DEFAULT_SIGLA);
        }
        
        $form          = $this->createForm(ServicoUnidadeType::class);
        $inlineForm    = $this->createForm(ServicoUnidadeType::class);
        $impressaoForm = $this->createForm(ImpressaoType::class, $unidade->getImpressao());

        return $this->render('@NovosgaSettings/default/index.html.twig', [
            'unidade' => $unidade,
            'locais' => $locais,
            'usuarios' => $usuarios,
            'form' => $form->createView(),
            'inlineForm' => $inlineForm->createView(),
            'impressaoForm' => $impressaoForm->createView(),
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
        $usuario = $this->getUser();
        $unidade = $usuario->getLotacao()->getUnidade();
        
        $servicos = $servicoService->servicosUnidade($unidade);
        
        $envelope = new Envelope();
        $envelope->setData($servicos);
        
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
            ->where('su.unidade = :unidade')
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
     * @Route("/servicos/{id}", name="novosga_settings_servicos_update")
     * @Method("POST")
     */
    public function updateServicoAction(Request $request, ServicoService $servicoService, $id)
    {
        $json = $request->getContent();
        $data = json_decode($json, true);
        
        $em = $this->getDoctrine()->getManager();
        $usuario = $this->getUser();
        $unidade = $usuario->getLotacao()->getUnidade();
        
        $su = $servicoService->servicoUnidade($unidade, $id);
        
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
    public function reiniciarContadorAction(Request $request, ServicoService $servicoService, Servico $servico)
    {
        $envelope = new Envelope();
        
        $em = $this->getDoctrine()->getManager();

        $usuario = $this->getUser();
        $unidade = $usuario->getLotacao()->getUnidade();

        $su = $servicoService->servicoUnidade($unidade, $servico);

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
    public function addServicoUsuarioAction(
        Request $request,
        ServicoService $servicoService,
        Usuario $usuario,
        Servico $servico
    ) {
        $em = $this->getDoctrine()->getManager();
        $unidade = $this->getUser()->getLotacao()->getUnidade();
        $envelope = new Envelope();
        
        $servicoUnidade = $servicoService->servicoUnidade($unidade, $servico);

        if (!$servicoUnidade) {
            throw new Exception(_('Serviço inválido'));
        }

        $servicoUsuario = new ServicoUsuario();
        $servicoUsuario->setUsuario($usuario);
        $servicoUsuario->setServicoUnidade($servicoUnidade);
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
    public function removeServicoUsuarioAction(
        Request $request,
        ServicoService $servicoService,
        Usuario $usuario,
        Servico $servico
    ) {
        $em = $this->getDoctrine()->getManager();
        $unidade = $this->getUser()->getLotacao()->getUnidade();
        $envelope = new Envelope();
        
        $servicoUnidade = $servicoService->servicoUnidade($unidade, $servico);

        if (!$servicoUnidade) {
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
    public function updateServicoUsuarioAction(
        Request $request,
        ServicoService $servicoService,
        Usuario $usuario,
        Servico $servico
    ) {
        $em = $this->getDoctrine()->getManager();
        $unidade = $this->getUser()->getLotacao()->getUnidade();
        $envelope = new Envelope();
        
        $servicoUnidade = $servicoService->servicoUnidade($unidade, $servico);

        if (!$servicoUnidade) {
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
