<?php

namespace Novosga\SettingsBundle\Controller;

use Exception;
use Novosga\Entity\Local;
use Novosga\Entity\Lotacao;
use Novosga\Entity\Servico;
use Novosga\Entity\Usuario;
use Novosga\Entity\Contador;
use Novosga\Http\Envelope;
use Novosga\Service\AtendimentoService;
use Novosga\Service\ServicoService;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Novosga\SettingsBundle\Form\ServicoUnidadeType;
use Novosga\SettingsBundle\Form\ImpressaoType;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;

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
    public function indexAction(Request $request)
    {
        $em = $this->getDoctrine()->getManager();
        
        $usuario = $this->getUser();
        $unidade = $usuario->getLotacao()->getUnidade();
        
        $service = new ServicoService($em);

        // locais disponiveis
        $locais = $em
                    ->getRepository(Local::class)
                    ->findBy([], ['nome' => 'ASC']);

        // usuarios da unidade
        $lotacoes = $em
                    ->getRepository(Lotacao::class)
                    ->getLotacoesUnidade($unidade);
        
        $servicos = $service->servicosUnidade($unidade);
        
        $usuarios = array_map(function (Lotacao $lotacao) use ($service, $unidade, $servicos) {
            $usuario = $lotacao->getUsuario();
            $servicosUsuario = $service->servicosUsuario($unidade, $usuario);
            
            $data  = $usuario->jsonSerialize();
            $data['servicos'] = [];
                    
            foreach ($servicos as $su) {
                foreach ($servicosUsuario as $servicoUsuario) {
                    $contains = $servicoUsuario->getServico()->getId() === $su->getServico()->getId();
                    if ($contains) {
                        $data['servicos'][] = $su;
                    }
                }
            }
            
            return $data;
        }, $lotacoes);

        if (count($locais)) {
            $local = $locais[0];
            $service->updateUnidade($unidade, $local, self::DEFAULT_SIGLA);
        }
        
        $form = $this->createForm(ServicoUnidadeType::class);
        $inlineForm = $this->createForm(ServicoUnidadeType::class);
        $impressaoForm = $this->createForm(ImpressaoType::class, $unidade->getImpressao());

        return $this->render('NovosgaSettingsBundle:default:index.html.twig', [
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
    public function servicosAction(Request $request)
    {
        $em = $this->getDoctrine()->getManager();
        $usuario = $this->getUser();
        $unidade = $usuario->getLotacao()->getUnidade();
        
        $service = new ServicoService($em);
        $servicos = $service->servicosUnidade($unidade);
        
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
            ->from(\Novosga\Entity\Contador::class, 'e')
            ->join('e.servico', 's')
            ->join(\Novosga\Entity\ServicoUnidade::class, 'su', 'WITH', 'su.servico = s')
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
    public function updateServicoAction(Request $request, $id)
    {
        $json = $request->getContent();
        $data = json_decode($json, true);
        
        $em = $this->getDoctrine()->getManager();
        $usuario = $this->getUser();
        $unidade = $usuario->getLotacao()->getUnidade();
        
        $service = new ServicoService($em);
        $su = $service->servicoUnidade($unidade, $id);
        
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
        try {
            $em = $this->getDoctrine()->getManager();
            $usuario = $this->getUser();
            $unidade = $usuario->getLotacao()->getUnidade();
            
            $data = json_decode($request->getContent(), true);
            
            $form = $this->createForm(ImpressaoType::class, $unidade->getImpressao());
            $form->submit($data);
            
            $em->merge($unidade);
            $em->flush();
            
            $envelope->setData($unidade);
            
        } catch (Exception $e) {
            $envelope->exception($e);
        }

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
        try {
            $em = $this->getDoctrine()->getManager();
            
            $usuario = $this->getUser();
            $unidade = $usuario->getLotacao()->getUnidade();
            
            $service = new ServicoService($em);
            $su = $service->servicoUnidade($unidade, $servico);
            
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
            
        } catch (Exception $e) {
            $envelope->exception($e);
        }

        return $this->json($envelope);
    }

    /**
     * @param Request $request
     * @return Response
     * 
     * @Route("/acumular_atendimentos", name="novosga_settings_acumular_atendimentos")
     * @Method("POST")
     */
    public function reiniciarAction(Request $request)
    {
        $envelope = new Envelope();
        try {
            $em = $this->getDoctrine()->getManager();
            $usuario = $this->getUser();
            $unidade = $usuario->getLotacao()->getUnidade();
            
            $service = new AtendimentoService($em);
            $service->acumularAtendimentos($unidade);
        } catch (Exception $e) {
            $envelope->exception($e);
        }

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
        
        try {
            $service = new ServicoService($em);
            $servicoUnidade = $service->servicoUnidade($unidade, $servico);
            
            if (!$servicoUnidade) {
                throw new Exception(_('Serviço inválido'));
            }
            
            $servicoUsuario = new \Novosga\Entity\ServicoUsuario();
            $servicoUsuario->setUsuario($usuario);
            $servicoUsuario->setServicoUnidade($servicoUnidade);
            $servicoUsuario->setPeso(1);
            
            $em->persist($servicoUsuario);
            $em->flush();
            
        } catch (Exception $e) {
            $envelope->exception($e);
        }
        
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
        
        try {
            $service = new ServicoService($em);
            $servicoUnidade = $service->servicoUnidade($unidade, $servico);
            
            if (!$servicoUnidade) {
                throw new Exception(_('Serviço inválido'));
            }
            
            $servicoUsuario = $em->getRepository(\Novosga\Entity\ServicoUsuario::class)
                                ->findOneBy([
                                    'usuario' => $usuario,
                                    'servico' => $servico,
                                    'unidade' => $unidade
                                ]);
            
            $em->remove($servicoUsuario);
            $em->flush();
            
        } catch (Exception $e) {
            $envelope->exception($e);
        }
        
        return $this->json($envelope);
    }
}
