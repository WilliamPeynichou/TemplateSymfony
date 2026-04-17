<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Organization;
use App\Entity\OrganizationInvitation;
use App\Entity\OrganizationMembership;
use App\Entity\User;
use App\Repository\OrganizationInvitationRepository;
use App\Repository\OrganizationRepository;
use App\Security\OrganizationVoter;
use App\Service\TransactionalMailer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
#[Route('/organizations')]
class OrganizationController extends AbstractController
{
    #[Route('', name: 'app_organization_index', methods: ['GET'])]
    public function index(OrganizationRepository $orgs): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        return $this->render('organization/index.html.twig', [
            'organizations' => $orgs->findForUser($user),
        ]);
    }

    #[Route('/new', name: 'app_organization_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        if ($request->isMethod('POST')) {
            $name = trim((string) $request->request->get('name'));
            if ('' === $name) {
                $this->addFlash('error', 'Le nom est requis.');

                return $this->redirectToRoute('app_organization_new');
            }

            /** @var User $user */
            $user = $this->getUser();
            $org = (new Organization())->setName($name)->setOwner($user);
            $em->persist($org);

            $membership = (new OrganizationMembership())
                ->setOrganization($org)->setUser($user)
                ->setRole(OrganizationMembership::ROLE_OWNER);
            $em->persist($membership);

            $em->flush();
            $this->addFlash('success', 'Organisation créée.');

            return $this->redirectToRoute('app_organization_show', ['id' => $org->getId()]);
        }

        return $this->render('organization/new.html.twig');
    }

    #[Route('/{id}', name: 'app_organization_show', methods: ['GET'])]
    public function show(Organization $org): Response
    {
        $this->denyAccessUnlessGranted(OrganizationVoter::MEMBER, $org);

        return $this->render('organization/show.html.twig', [
            'organization' => $org,
        ]);
    }

    #[Route('/{id}/invite', name: 'app_organization_invite', methods: ['POST'])]
    public function invite(
        Organization $org,
        Request $request,
        EntityManagerInterface $em,
        TransactionalMailer $mailer,
    ): Response {
        $this->denyAccessUnlessGranted(OrganizationVoter::OWNER, $org);

        $email = trim((string) $request->request->get('email'));
        if ('' === $email) {
            $this->addFlash('error', 'Email requis.');

            return $this->redirectToRoute('app_organization_show', ['id' => $org->getId()]);
        }

        $invite = (new OrganizationInvitation())
            ->setOrganization($org)
            ->setEmail($email);

        $em->persist($invite);
        $em->flush();

        $link = $this->generateUrl('app_organization_accept_invite', ['token' => $invite->getToken()], 0);
        $mailer->sendOrganizationInvitation($email, $org->getName(), $link);

        $this->addFlash('success', sprintf('Invitation envoyée à %s.', $email));

        return $this->redirectToRoute('app_organization_show', ['id' => $org->getId()]);
    }

    #[Route('/accept/{token}', name: 'app_organization_accept_invite', methods: ['GET', 'POST'])]
    public function accept(
        string $token,
        OrganizationInvitationRepository $invites,
        EntityManagerInterface $em,
    ): Response {
        $invite = $invites->findValidByToken($token);
        if (!$invite || !$invite->isPending()) {
            throw $this->createNotFoundException('Invitation invalide ou expirée.');
        }

        /** @var User $user */
        $user = $this->getUser();
        if (strtolower($invite->getEmail()) !== strtolower((string) $user->getEmail())) {
            $this->addFlash('error', 'Cette invitation est destinée à une autre adresse email.');

            return $this->redirectToRoute('app_organization_index');
        }

        $org = $invite->getOrganization();
        if (!$org) {
            throw $this->createNotFoundException('Organisation introuvable.');
        }
        if (!$org->hasMember($user)) {
            $membership = (new OrganizationMembership())
                ->setOrganization($org)->setUser($user)
                ->setRole($invite->getRole());
            $em->persist($membership);
        }

        $invite->setStatus(OrganizationInvitation::STATUS_ACCEPTED);
        $em->flush();

        $this->addFlash('success', sprintf('Vous avez rejoint %s.', $org->getName()));

        return $this->redirectToRoute('app_organization_show', ['id' => $org->getId()]);
    }
}
