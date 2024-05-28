<?php

namespace App\Controller;

use App\Entity\Client;
use App\Entity\Dette;
use App\Entity\Search;
use App\Form\DetteType;
use App\Form\SearchType;
use App\Repository\ClientRepository;
use App\Repository\DetteRepository;
use Doctrine\ORM\EntityManagerInterface;
use MercurySeries\FlashyBundle\FlashyNotifier;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Component\Security\Core\Security;

class DetteController extends AbstractController
{
    #[Route('/dette', name: 'dette_liste')]
    public function index(DetteRepository $dette,Request $request, PaginatorInterface $paginator): Response
    {
        $sommeMontantImpaye = $dette->findSumMontantImpaye();

        $d = new Dette();
        $form = $this->createForm(DetteType::class, $d, array(
            'action' => $this->generateUrl('dette_add'),
        ));

        $search = new Search();
        $form2 = $this->createForm(SearchType::class, $search);
        $form2->handleRequest($request);

        $nom = $search->getNom();
        $pagination = $paginator->paginate(
            ($nom !== null && $nom !== '') ? $dette->findByName($nom) : $dette->findAllOrderedByDate(),
            $request->query->get('page', 1),
            20
        );

        return $this->render('dette/liste.html.twig', [
            'controller_name' => 'DetteController',
            'pagination' => $pagination,
            'sommeMontantImpaye' => $sommeMontantImpaye,
            'form2' => $form2->createView(),
            'form' => $form->createView(),

        ]);
        return $this->render('dette/liste.html.twig');
    }

    #[Route('/dette/add', name: 'dette_add')]
    public function add(EntityManagerInterface $manager, Request $request, FlashyNotifier $notifier, ClientRepository $repository, DetteRepository $dettes): Response
    {
        $dette = new Dette();
        $form = $this->createForm(DetteType::class, $dette);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $client = $dette->getClient();
            $client = $manager->getRepository(Client::class)->find($client->getId());
            if ($client) {
                $dette->setClient($client)
                        ->setDateCreated(new \DateTime())
                        ->setReste($dette->getMontantDette())
                        ->setStatut('impayé');
            }

            $c = $dettes->findAllOrderedByDate();
                foreach ( $c as $s) {
                    if ( $dette->getClient()->getNom() === $s->getClient()->getNom() && $s->getStatut() == "impayé" && $s->getReste() != 0) {
                        $this->addFlash('danger',$s->getClient()->getNom().' a déjà une dette non payée.');
                        return $this->redirectToRoute('dette_liste');
                    }
                }
            $manager->persist($dette);
            $manager->flush();
            $this->addFlash('success','La dette a été enregistrée avec succès.');
        }
        return $this->redirectToRoute('dette_liste');
    }

    #[Route('/dette/delete/{id}', name: 'dette_delete')]
    public function delete(Dette $dette, DetteRepository $repository){
        if ($dette->getStatut() != 'payée'){
            $this->addFlash('danger', 'La dette n\'a pas encore été réglée.');
            return $this->redirectToRoute('dette_liste');
        }
        $repository->remove($dette,true);
        $this->addFlash('success', 'La dette a été supprimé avec succès');
        return $this->redirectToRoute('dette_liste');
    }

    #[Route('/dette/info/{id}', name: 'dette_info')]
    public function info(Dette $dette, DetteRepository $repository, Request $request)
    {
        $infos = $dette->getPaiement()->getOwner();
        // Renvoie les informations dans la vue du modal
        return $this->render('dette/detail.html.twig', [
            'infos' => $infos,
        ]);
    }

    #[Route('/dette/edit/{id}', name: 'edit_dette')]
    public function editManual($id, DetteRepository $detteRepository, Request $request, EntityManagerInterface $entityManager, Security $security)
    {

        $user = $security->getUser();
        if (!$user) {
            // Redirigez l'utilisateur vers la page de connexion si non connecté
            return $this->redirectToRoute('app_login');
        }
        $dette = $detteRepository->find($id);

        // Vérifier si la dette existe
        if (!$dette) {
            $this->addFlash('danger', 'Dette non trouvée');
            return $this->redirectToRoute("dette_liste");
        }

        // Récupérer les données du formulaire soumis
        $montantDette = $request->request->get('montant_dette');
        $commentaire = $request->request->get('commentaire');

        // Traiter les données soumises
        if ($request->isMethod('POST')) {
            // Mettre à jour la dette
            $dette->setMontantDette($montantDette);
            $dette->setReste($montantDette);
            $dette->setCommentaire($commentaire);
            $entityManager->flush();

            $this->addFlash('success', 'La dette a été modifiée avec succès');

            return $this->redirectToRoute("dette_liste");
        }

        // Afficher le formulaire de modification
        return $this->render('dette/edit.html.twig', [
            'dette' => $dette,
        ]);
    }

    #[Route('/recherche', name: 'recherche_dette')]
    public function rechercheDette(Request $request, DetteRepository $detteRepository, PaginatorInterface $paginator): JsonResponse
    {
        $searchValue = $request->query->get('search');

        if ($searchValue) {
            $results = $detteRepository->findByName($searchValue);
        } else {
            $results = $detteRepository->findAllOrderedByDate();
        }

        // Paginer les résultats
        $pagination = $paginator->paginate(
            $results,
            $request->query->getInt('page', 1),
            20
        );

        // Formatage des résultats paginés en tableau associatif
        $formattedResults = [];
        foreach ($pagination as $result) {
            $formattedResults[] = [
                'client' => $result->getClient()->getNom(),
                'montantDette' => $result->getMontantDette(),
                'reste' => $result->getReste(),
                'statut' => $result->getStatut(),
                'dateCreated' => $result->getDateCreated()->format('d/m/Y'),
                'infoUrl' => $this->generateUrl('dette_info', ['id' => $result->getId()]),
                'editUrl' => $this->generateUrl('edit_dette', ['id' => $result->getId()]),
                'deleteUrl' => $this->generateUrl('dette_delete', ['id' => $result->getId()]),
                // Ajoutez d'autres champs si nécessaire
            ];
        }

        return new JsonResponse([
            'results' => $formattedResults,
            'pagination' => [
                'totalItems' => $pagination->getTotalItemCount(),
                'itemsPerPage' => $pagination->getItemNumberPerPage(),
                'currentPage' => $pagination->getCurrentPageNumber(),
                'totalPages' => $pagination->getPageCount(),
            ],
        ]);
    }
}