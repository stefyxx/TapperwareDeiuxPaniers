<?php

namespace App\Controller;

use App\Entity\DetailProduitLocation;
use App\Entity\Panier;
use App\Form\PanierType;
use App\Repository\PanierRepository;
use App\Repository\PrototypeProduitRepository;
use App\Repository\RestaurantRepository;
use DateTime;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/panier')]
class PanierController extends AbstractController
{
    /*#[Route('/', name: 'app_panier_index', methods: ['GET'])]
    public function index(PanierRepository $panierRepository): Response
    {
        return $this->render('panier/index.html.twig', [
            'paniers' => $panierRepository->findAll(),
        ]);
    }*/

    //il paniere é registrato con 3 actions, NON CREO UN DetailProduitLocation!:
    //sono su lista produtti e clicco sul prodotto-> rediretto a panier 'index'
    //riclicco sullo stesso prodotto -> 'add'
    //fleggo affianco -> 'remove' all
    #[Route('/', name: 'app_panier_index')]
    public function index(SessionInterface $session, PrototypeProduitRepository $productRepository): Response
    {
        //paniere che ha id di prodotto, oppure vuoto
        $panier = $session->get('panier', []);
        //rifaccio un paniere che ha più di un id e le quantità
        $panierWithData = [];
        foreach ($panier as $id => $quantity) {
            $panierWithData[] = [
                //'id' => $id,
                'product' => $productRepository->find($id),        //new product()->getReposiroty()->find(id);
                'quantity' => $quantity
            ];
        }

        $total = 0;
        foreach ($panierWithData as $item) {
            //totale di 1 prodotto
            $totalItem = $item['product']->getPrixLocationUnitaire() * $item['quantity'];
            //totale di tutto
            $total += $totalItem;
        }

        return $this->render('panier/index.html.twig', [
            'controller_name' => 'CartController',
            'items' => $panierWithData,
            'totale' => $total
        ]);
    }

    //leal
    #[Route('/afficher', name: 'panier_afficher')]
    public function afficher(SessionInterface $session, PrototypeProduitRepository $repProduit): Response
    {
        $panier = $session->get('panier', new Panier());

        $vars = ['panier' => $panier];
        return $this->render('panier/panier_afficher.html.twig', $vars);
    }

    #[Route(path: '/panier/add/{id}', name: 'produit_add')]
    public function add($id, SessionInterface $session)
    {
        //é un Resto che fa il paniere -> loggato -> Resto

        $panier = $session->get('panier', []);

        if (!empty($panier[$id])) {
            //se riclicco sullo stesso prodotto, aumento la quantità
            $panier[$id] += 10;
        } else {
            //se clicco 1 volta sul prodotto, aggiungo quantità 1
            $panier[$id] = 10;
        }
        $session->set('panier', $panier);
        return $this->redirectToRoute('app_panier_index');
    }

    //entra in show per attivare questo paniere (leal)
    #[Route('/panier/add', name: 'panier_add_produit_plusieurs')]
    public function addProduitPlusieurs(Request $req,SessionInterface $session,PrototypeProduitRepository $repProduit,RestaurantRepository $repResto
    ): Response 
    {
        //metodo che mi permette di recuperare i valori delle variabili del formulario (name=""):

        //é un Resto che fa il paniere -> loggato -> Resto
        $id_resto = $req->request->get('id_resto');

        $id = $req->request->get('id');
        $quantite = $req->request->get('quantite');

        //in panier non ho le quantità, 
        //queste sono in DetailProduitLocation()

        $panier = $session->get('panier', new Panier());
        if (!empty($panier)) {

            $resto = $repResto->find($id_resto);
            $panier->setRestaurant($resto);
            $panier->setDatePaiment(new DateTime());
            $resto->addPanier($panier);

            //fare funsione a parte=
            foreach ($panier->getDetailProduitLocations() as $detailProd) {
                if($detailProd->getPrototypeProduit()->getId() == $id){
                    $detailProd->setMontant($quantite);
                }
            }

            $detail = new DetailProduitLocation();

            $produit = $repProduit->find($id);
            $detail->setPrototypeProduit($produit);
            $detail->setQuantiteTotal($quantite);
            $detail->setQuantiteResteRendre(0);

            $prixUnitaire = $produit->getPrixLocationUnitaire();
            $montant = $prixUnitaire * $quantite;
            $detail->setMontant($montant);
            $detail->setMontantParUnite($prixUnitaire);


            //fare una funzione che scala lo stock
            //$repProduit->removeStock($quantite);

            $panier->addDetailProduitLocation($detail);
            $detail->setPanier($panier);

            $total=0;
            foreach ($panier->getDetailProduitLocations() as $detailProd) {
                $montant= $detailProd->getMontant();
                $total+=$montant;
            }
            $panier->setTotal($total);

            
        }

        $session->set('panier', $panier);
        return $this->redirectToRoute('panier_afficher');
    }

    #[Route(path: '/panier/remove/{id}', name: 'produit_remove')]
    public function remove($id, SessionInterface $session)
    {
        //voglio sopprimere un articolo dal paniere
        $panier = $session->get('panier', []);

        if (!empty($panier[$id])) {
            //unset($panier[$id]);
            $panier[$id] -= 10;
        }
        $session->set('panier', $panier);
        return $this->redirectToRoute('app_panier_index');
    }

    //leal
    #[Route('/panier/effacer/detail/{id}', name: 'panier_effacer_detail')]
    public function panierEffacerDetail(Request $req,SessionInterface $session) 
    {

        // dump ($session->get("panier"));
        // dump ($detail);

        // on efface de la session et de la BD
        $panier = $session->get("panier");

        // Effacer de la SESSION
        // On cherche le détail qui contient le produit dans
        // la commande de la session
        
        // Commande avant
        // dump($panier->getDetails());

        // on parcour tous les détails et on cherche 
        // celui qui contient le produit recherché
        $detailsCommandeSession = $panier->getDetails();
        foreach ($panier->getDetails() as $key => $detailSession){
            if ($req->get('id') == $detailSession->getProduit()->getId()){
                $detailsCommandeSession->removeElement($detailSession);
                // plus besoin de chercher, on redirige
                return $this->redirectToRoute('panier_afficher');

            }
        }
        // Commande après
        // dd($panier->getDetails());

        // on affiche à nouveau le panier de toute façon
        return $this->redirectToRoute('panier_afficher');
    }




    #[Route('/new', name: 'app_panier_new', methods: ['GET', 'POST'])]
    public function new(Request $request, PanierRepository $panierRepository): Response
    {
        $panier = new Panier();
        $form = $this->createForm(PanierType::class, $panier);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $panierRepository->add($panier);
            return $this->redirectToRoute('app_panier_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->renderForm('panier/new.html.twig', [
            'panier' => $panier,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_panier_show', methods: ['GET'])]
    public function show(Panier $panier): Response
    {
        return $this->render('panier/show.html.twig', [
            'panier' => $panier,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_panier_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Panier $panier, PanierRepository $panierRepository): Response
    {
        $form = $this->createForm(PanierType::class, $panier);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $panierRepository->add($panier);
            return $this->redirectToRoute('app_panier_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->renderForm('panier/edit.html.twig', [
            'panier' => $panier,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_panier_delete', methods: ['POST'])]
    public function delete(Request $request, Panier $panier, PanierRepository $panierRepository): Response
    {
        if ($this->isCsrfTokenValid('delete' . $panier->getId(), $request->request->get('_token'))) {
            $panierRepository->remove($panier);
        }

        return $this->redirectToRoute('app_panier_index', [], Response::HTTP_SEE_OTHER);
    }
}
