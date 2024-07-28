<?php

namespace App\Controller;

use App\Entity\Item;
use App\Entity\User;
use App\Entity\Category;
use App\Entity\ToDoList;
use App\Form\AddItemType;
use App\Form\AddListType;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class CheckitController extends AbstractController
{
    #[Route('/mes-listes', name: 'mes-listes')]
    public function index(EntityManagerInterface $em): Response
    {
        $lists = $em->getRepository(ToDoList::class)->findAll() ;
        $category = $em->getRepository(Category::class)->findAll() ;
        $items = $em->getRepository(Item::class)->findAll() ;

        return $this->render('checkit/mes-listes.html.twig', [
            'controller_name' => 'CheckitController',
            'lists' => $lists,
            'category' => $category,
            'items' => $items,
        ]);
    }

    public function findListIcon($icon, EntityManagerInterface $em, Connection $connection): Response
    {

        // Trouver la catégorie par son icône
        $category = $em->getRepository(Category::class)->findOneBy(['icon' => $icon]);
        
        if (!$category) {
            throw $this->createNotFoundException('Catégorie non trouvée pour cette icône.');
        }

        $categoryId = $category->getId();

        // Préparer la requête SQL avec INNER JOIN pour récupérer les listes et leurs icônes de catégorie
        $requete = "SELECT tdl.id, tdl.title, tdl.user_id, c.name AS category_name, c.icon AS category_icon 
                    FROM to_do_list tdl 
                    INNER JOIN category c ON tdl.category_id = c.id 
                    WHERE c.id = :category_id";

        // Exécuter la requête
        $stmt = $connection->prepare($requete);
        $stmt->bindValue('category_id', $categoryId);
        $result = $stmt->executeQuery();
        $lists = $result->fetchAllAssociative();
        
        dd($lists);

        return $this->render('checkit/mes-listes.html.twig', [
            'controller_name' => 'CheckitController',
            'lists' => $lists,
            'category' => $category,
        ]);
    }

    #[Route('/ajout-liste', name: 'ajout-liste')]
    public function addList(EntityManagerInterface $em, Request $request): Response
    {
        $addList = new ToDoList();
        $formList = $this->createForm(AddListType::class, $addList);
        $formList->handleRequest($request);
        if ($formList->isSubmitted() && $formList->isValid()) {

            $user = $this->getUser();
            $addList->setUser($user);

            $em->persist($addList);
            $em->flush();

            return $this->redirectToRoute('modif-list', ['id' => $addList->getId()]);
        }

        return $this->render('checkit/ajout-liste.html.twig', [
            'controller_name' => 'CheckitController',
            'formList' => $formList->createView(),

        ]);
    }

    #[Route('/mes-listes/{id}', name: 'modif-list')]
    public function modifList($id, EntityManagerInterface $em, Request $request): Response
    {
        $list = $em->getRepository(ToDoList::class)->find($id);

        if (!$list) {
            throw $this->createNotFoundException('Liste non trouvée.');
        }

        $formList = $this->createForm(AddListType::class, $list);
        $formList->handleRequest($request);

        $addItem = new Item();
        $formItem = $this->createForm(AddItemType::class, $addItem);
        $formItem->handleRequest($request);

        if ($formList->isSubmitted() && $formList->isValid()) {
            $em->persist($list);
            $em->flush();

            return $this->redirectToRoute('modif-list', ['id' => $list->getId()]);
        }

        if ($formItem->isSubmitted() && $formItem->isValid()) {
            $addItem->setList($list); 
            $addItem->setStatus(false); 
            $em->persist($addItem);
            $em->flush();

            return $this->redirectToRoute('modif-list', ['id' => $list->getId()]);
        }

        $allItems = $em->getRepository(Item::class)->findBy(['list' => $list]);


        return $this->render('checkit/modif-liste.html.twig', [
            'controller_name' => 'CheckitController',
            'formList' => $formList->createView(),
            'formItem' => $formItem->createView(),
            'list' => $list,
            'allItems' => $allItems,
        ]);
    }

    #[Route('/mes-listes/{id}/status', name: 'status-update')]
    public function updateItem($id, EntityManagerInterface $em): Response
    {
        $item = $em->getRepository(Item::class)->find($id);

        if (!$item) {
            throw $this->createNotFoundException('Item not found');
        }

        if ($item->isStatus() == true) {
            $item->setStatus(false);
        } else {
            $item->setStatus(true);
        }

        $em->persist($item);
        $em->flush();

        return $this->redirectToRoute('modif-list', ['id' => $item->getList()->getId()]);
    }

    #[Route('/suppr-list', name: 'suppr-list')]
    public function deleteList(): Response
    {

        return $this->render('checkit/mes-listes.html.twig', [
            'controller_name' => 'CheckitController',
        ]);
    }

    #[Route('/suppr-item/{id}/delete', name: 'suppr-item')]
    public function supprItem($id, EntityManagerInterface $em): Response
    {

        $item = $em->getRepository(Item::class)->find($id);
        $em->remove($item);
        $em->flush();

        $this->addFlash('success', 'Tâche supprimée avec succès');

        return $this->redirectToRoute('modif-list', ['id' => $item->getList()->getId()]);
    }

    #[Route('/modif-item/{id}/update', name: 'modif-item', methods: ['GET', 'POST'])]
    public function modifItem($id, EntityManagerInterface $em, Request $request): Response
    {
        $item = $em->getRepository(Item::class)->find($id);
        
        if (!$item) {
            throw $this->createNotFoundException('Item not found');
        }
    
        if ($request->isMethod('POST')) {
            $newName = $request->request->get('name');
            if ($newName) {
                $item->setName($newName);
                $em->flush();
                $this->addFlash('success', 'L\'item a été modifié avec succès.');
            }
            return $this->redirectToRoute('modif-list', ['id' => $item->getList()->getId()]);
        }
    
        // Si c'est une requête GET, affichez simplement le formulaire
        return $this->render('checkit/modif-item.html.twig', [
            'item' => $item,
        ]);
    }
}   