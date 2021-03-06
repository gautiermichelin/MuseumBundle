<?php

// src/KE/MuseumBundle/Controller/EtageController.php

namespace KE\MuseumBundle\Controller;

use KE\MuseumBundle\Entity\Etage;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Doctrine\ORM\Query\ResultSetMapping;

class EtageController extends Controller
{

	public function addAction(Request $request)
    {
		$em = $this->getDoctrine()->getManager();
		$etages = $em->getRepository('KEMuseumBundle:Etage')->findAll();
		$etage = new Etage();
		$armoires = $em->getRepository('KEMuseumBundle:Armoire');
		$erreur = false;

		$form = $this->get('form.factory')->createBuilder('form', $etage)
			->add('code','text')
			->add('idArmoire', 'text')
			->add('longueur','text')
			->add('profondeur','text')
			->add('hauteur','text')
			->add('save','submit')
			->getForm()
			;
			
		$form->handleRequest($request);
			
		if ($form->isValid()) {		
			foreach($etages as $val){
				if($etage->getCode() == $val->getCode()){
					$erreur = true;
				}
			}
			if($erreur){
				return $this->redirect($this->generateUrl('etage_add_erreur', array('code' => $etage->getCode()
				, 'type' => 'codeAlreadyExist' )));
			}
			else{
				if($armoires->findOneByCode($etage->getIdArmoire()) == null)
				{
					return $this->redirect($this->generateUrl('etage_add_erreur', array(
					'code' => $etage->getIdArmoire(), 'type' => 'armoireNotExist')));
				}
				$etage->onCreate();	
				$etage->setIdArmoire($armoires->findOneByCode($etage->getIdArmoire())->getId());
				$count = $em->getRepository('KEMuseumBundle:Etage')->getNbForArmoire($etage->getIdArmoire());
			
				if($count == null){
				$count=0;
				}
				$count+=1;
				$etage->setOrdreArmoire($count);
				$em->persist($etage);
				$em->flush();
				
				return $this->redirect($this->generateUrl('home_action', array('type' => 'succes', 'codeMessage' => '4')));
			}
		}
			
		return $this->render('KEMuseumBundle:Etage:add.html.twig', array(
			'form' => $form->createView(),
		));
    } 

	
	public function editAction($code, Request $request)
    {
       $em = $this->getDoctrine()->getManager();

		$etage = $em->getRepository('KEMuseumBundle:Etage')->findOneByCode($code);

		if (null === $etage) {
			throw new NotFoundHttpException("L'�tage de num�ro ".$code." n'existe pas.");
		}

		
		$form = $this->get('form.factory')->createBuilder('form', $etage)
			->add('code','text')
			->add('longueur','text')
			->add('profondeur','text')
			->add('hauteur','text')
			->add('save','submit')
			->getForm()
			;

		if ($form->handleRequest($request)->isValid()) {
			$em->flush();
			return $this->redirect($this->generateUrl('home_action', array('type' => 'succes', 'codeMessage' => '6')));
		}

		return $this->render('KEMuseumBundle:Etage:edit.html.twig', array(
			'form'   => $form->createView(),
			'etage'  => $etage
			));
    } 
	
	public function deleteAction($code, Request $request)
    {
        $em = $this->getDoctrine()->getManager();

		$etage = $em->getRepository('KEMuseumBundle:Etage')->findOneByCode($code);
		$ordres = $em->getRepository('KEMuseumBundle:Ordre')->findByIdEtage($etage->getId());


		if (null === $code) {
			throw new NotFoundHttpException("L'�tage de num�ro ".$code." n'existe pas.");
		}

		
		$form = $this->get('form.factory')->createBuilder('form', $etage)
			->add('code','text')
			->add('longueur','text')
			->add('profondeur','text')
			->add('hauteur','text')
			->add('save','submit')
			->getForm()
			;

		if ($form->handleRequest($request)->isValid()) {
			foreach ($ordres as $or) {
				$or->setOrdre(null);
				$or->setPourcent(null);
				$or->setIdEtage(null);
				$em->persist($or);
			}
			$em->remove($etage);
			$em->flush();
			return $this->redirect($this->generateUrl('home_action', array('type' => 'succes', 'codeMessage' => '5')));
		}

		return $this->render('KEMuseumBundle:Etage:delete.html.twig', array(
			'form'   => $form->createView()
			));
    } 
	
	public function placementObjetAction($codeEtage, $codeObjet){
	
		$em = $this->getDoctrine()->getManager();
		$objet = $em->getRepository('KEMuseumBundle:Objet')->findOneByCode($codeObjet);
		$etage = $em->getRepository('KEMuseumBundle:Etage')->findOneByCode($codeEtage);
		$ordre = $em->getRepository('KEMuseumBundle:Ordre')->findOneByIdObjet($objet->getId());
		
		$ordre->setIdEtage($etage->getId());
		$etage->setPlaceDisponible($etage->getPlaceDisponible() - $objet->getLongueur());
		
		$count = $em->getRepository('KEMuseumBundle:Ordre')->getNbForEtage($etage->getId());
		
		if($count == null){
			$count=0;
		}
		$count+=1;
		$ordre->setOrdre($count);
		$ordre->setPourcent(($objet->getLongueur()*100)/$etage->getLongueur());
		
		$em->persist($ordre);
		$em->persist($etage);
		$em->flush();
	
		return $this->redirect($this->generateUrl('etage_consult', array(
			'code' => $codeEtage)));
	}
	
	public function retirerObjetAction($codeEtage, $codeObjet){
	
		$em = $this->getDoctrine()->getManager();
		$objet = $em->getRepository('KEMuseumBundle:Objet')->findOneByCode($codeObjet);
		$etage = $em->getRepository('KEMuseumBundle:Etage')->findOneByCode($codeEtage);
		$ordre = $em->getRepository('KEMuseumBundle:Ordre')->findOneByIdObjet($objet->getId());
		$ordres = $em->getRepository('KEMuseumBundle:Ordre')->findByIdEtage($etage->getId());
		
		foreach ($ordres as $or) {
			if($or->getOrdre() > $ordre->getOrdre() ){
				$or->setOrdre($or->getOrdre() - 1);
				$em->persist($or);
			}
		}
		
		$etage->setPlaceDisponible($etage->getPlaceDisponible() + $objet->getLongueur());
	
		$ordre->setIdEtage(null);
		$ordre->setOrdre(null);
		$ordre->setPourcent(null);
		
		$em->persist($ordre);
		$em->persist($etage);
		$em->flush();
	
		return $this->redirect($this->generateUrl('etage_consult', array(
			'code' => $codeEtage)));
	}
	
	public function indexConsultAction(Request $request)
    {
		$em = $this->getDoctrine()->getManager();
		$etages = $em->getRepository('KEMuseumBundle:Etage')->findAll();
		$armoires = $em->getRepository('KEMuseumBundle:Armoire')->findAll();
		
        $form = $this->get('form.factory')->createBuilder('form')
			->add('code','text')
			->add('save','submit')
			->getForm()
			;
			
		$form->handleRequest($request);
			
		if ($form->isValid()) {
			$em = $this->getDoctrine()->getManager();
			$code = $form->get('code')->getData();
			$etage = $em->getRepository('KEMuseumBundle:Etage')->findOneByCode($code);
			if($etage == null)
			{
				return $this->redirect($this->generateUrl('etage_index_consult_erreur', array(
				'code' => $code)));
			}
			else
			{	
				return $this->redirect($this->generateUrl('etage_consult', array(
				'code' => $code)));
			}	
		}	
		return $this->render('KEMuseumBundle:Etage:indexConsult.html.twig', array(
			'form' => $form->createView(), 'etages' => $etages, 'armoires' => $armoires
		));
    }   
	
	public function consultAction($code, Request $request)
    {
       $em = $this->getDoctrine()->getManager();

		$etage = $em->getRepository('KEMuseumBundle:Etage')->findOneByCode($code);
		$ordres = $em->getRepository('KEMuseumBundle:Ordre')->findByIdEtage($etage->getId(),array('ordre'=>'ASC'));
		$objets = $em->getRepository('KEMuseumBundle:Objet')->findAll();
		$armoires = $em->getRepository('KEMuseumBundle:Armoire')->findAll();

		if (null === $etage) {
			throw new NotFoundHttpException("L'�tage de num�ro ".$code." n'existe pas.");
		}

		return $this->render('KEMuseumBundle:Etage:consult.html.twig', array(
			'etage' => $etage, 'ordres' => $ordres, 'objets' => $objets, 'armoires' => $armoires
			));
    } 
	
	public function ordreUpAction($idEtage, $idObjet){
	
		$em = $this->getDoctrine()->getManager();
		$objet = $em->getRepository('KEMuseumBundle:Objet')->findOneById($idObjet);
		$etage = $em->getRepository('KEMuseumBundle:Etage')->findOneById($idEtage);
		$ordre = $em->getRepository('KEMuseumBundle:Ordre')->findOneByIdObjet($idObjet);
		$ordreUp = $em->getRepository('KEMuseumBundle:Ordre')
					->findOneBy(array('idEtage' => $idEtage,'ordre' => ($ordre->getOrdre())-1));
	
		$ordreUp->setOrdre($ordre->getOrdre());
		$ordre->setOrdre(($ordre->getOrdre())-1);
		
		$em->persist($ordre);
		$em->persist($ordreUp);
		$em->flush();
		
		return $this->redirect($this->generateUrl('etage_consult', array(
				'code' => $etage->getCode())));
	}
		
	public function ordreDownAction($idEtage, $idObjet){
	
		$em = $this->getDoctrine()->getManager();
		$objet = $em->getRepository('KEMuseumBundle:Objet')->findOneById($idObjet);
		$etage = $em->getRepository('KEMuseumBundle:Etage')->findOneById($idEtage);
		$ordre = $em->getRepository('KEMuseumBundle:Ordre')->findOneByIdObjet($idObjet);
		$ordreDown = $em->getRepository('KEMuseumBundle:Ordre')
					->findOneBy(array('idEtage' => $idEtage,'ordre' => ($ordre->getOrdre())+1));
	
		$ordreDown->setOrdre($ordre->getOrdre());
		$ordre->setOrdre(($ordre->getOrdre())+1);
		
		$em->persist($ordre);
		$em->persist($ordreDown);
		$em->flush();
		
		return $this->redirect($this->generateUrl('etage_consult', array(
				'code' => $etage->getCode())));
	}	
		
		
}
?>
