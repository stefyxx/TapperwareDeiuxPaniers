<?php

namespace App\Controller;

use App\Entity\DetailProduitLocation;
use App\Entity\Panier;
use App\Form\DetailProduitLocationType;
use App\Repository\DetailProduitLocationRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Doctrine\Persistence\ManagerRegistry;
use DateTime;
use Symfony\Component\Validator\Constraints\Length;

#[Route('/detail/location')]
class DetailLocationController extends AbstractController
{
    #[Route('/', name: 'app_detail_location_index', methods: ['GET'])]
    public function index(DetailProduitLocationRepository $detailProduitLocationRepository): Response
    {
        return $this->render('detail_location/index.html.twig', [
            'detail_produit_locations' => $detailProduitLocationRepository->findAll(),
        ]);
    }

    #[Route('/new', name: 'app_detail_location_new', methods: ['GET', 'POST'])]
    public function new(Request $request, DetailProduitLocationRepository $detailProduitLocationRepository): Response
    {
        $detailProduitLocation = new DetailProduitLocation();
        $form = $this->createForm(DetailProduitLocationType::class, $detailProduitLocation);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $detailProduitLocationRepository->add($detailProduitLocation);
            return $this->redirectToRoute('app_detail_location_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->renderForm('detail_location/new.html.twig', [
            'detail_produit_location' => $detailProduitLocation,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_detail_location_show', methods: ['GET'])]
    public function show(DetailProduitLocation $detailProduitLocation): Response
    {
        return $this->render('detail_location/show.html.twig', [
            'detail_produit_location' => $detailProduitLocation,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_detail_location_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, DetailProduitLocation $detailProduitLocation, DetailProduitLocationRepository $detailProduitLocationRepository): Response
    {
        $form = $this->createForm(DetailProduitLocationType::class, $detailProduitLocation);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $detailProduitLocationRepository->add($detailProduitLocation);
            return $this->redirectToRoute('app_detail_location_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->renderForm('detail_location/edit.html.twig', [
            'detail_produit_location' => $detailProduitLocation,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_detail_location_delete', methods: ['POST'])]
    public function delete(Request $request, DetailProduitLocation $detailProduitLocation, DetailProduitLocationRepository $detailProduitLocationRepository): Response
    {
        if ($this->isCsrfTokenValid('delete'.$detailProduitLocation->getId(), $request->request->get('_token'))) {
            $detailProduitLocationRepository->remove($detailProduitLocation);
        }

        return $this->redirectToRoute('app_detail_location_index', [], Response::HTTP_SEE_OTHER);
    }

    #[Route('/passer', name: 'commande_passer')]
    public function commandePasser(SessionInterface $session,ManagerRegistry $doctrine): Response 
    {

        $commandePanier = $session->get("panier");
        $commandeBD = new Panier();
        $em = $doctrine->getManager();
        // on initisalise tout ce qu'on a de la commande avant de faire le persist
        // (client - $this->getUser(), dateCreation, etc...)
        // et puis on copie les détails du panier 
        $commandeBD->setDatePaiment(new DateTime());
        $em->persist($commandeBD);
        $em->flush();

        // on a maintenant une commande avec un id
        foreach ($commandePanier->getDetails() as $detail) {
            $commandeBD->addDetailProduitLocation($detail);
            
            
        }
        $em->persist($commandeBD);
        $em->flush(); // mettre à jour une fois les détails sont là

        $total = 0.0;
        $listaDetails= $commandeBD->getDetailProduitLocations();
        for ($i=0; $i <count($listaDetails); $i++) {
            //$listaDetails é una Collection <int, type>
            //$total+= $listaDetails->getMontant();
        }

        $vars = ['commandeBD' => $commandeBD,
    'total'=> $total];
        return $this->render('detail_location/commande_passer.html.twig', $vars);
    }
}
