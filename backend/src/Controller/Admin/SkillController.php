<?php

namespace App\Controller\Admin;

use App\Entity\Skill;
use App\Form\SkillType;
use App\Repository\SkillRepository;
use App\Service\SkillCompiler;
use App\Service\SkillYamlValidator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin')]
class SkillController extends AbstractController
{
    #[Route('/skills', name: 'app_admin_skill_list')]
    public function list(SkillRepository $skillRepository): Response
    {
        return $this->render('admin/skill/list.html.twig', [
            'skills' => $skillRepository->findBy([], ['skillId' => 'ASC']),
        ]);
    }

    #[Route('/skills/new', name: 'app_admin_skill_create')]
    public function create(
        Request $request,
        EntityManagerInterface $em,
        SkillYamlValidator $validator,
    ): Response {
        $skill = new Skill();
        $form = $this->createForm(SkillType::class, $skill, ['is_create' => true]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $yamlFile = $form->get('yamlFile')->getData();
            if ($yamlFile) {
                $yamlContent = file_get_contents($yamlFile->getPathname());
                $errors = $validator->validate($yamlContent);

                if (!empty($errors)) {
                    foreach ($errors as $error) {
                        $this->addFlash('error', $error);
                    }
                    return $this->render('admin/skill/form.html.twig', [
                        'form' => $form,
                        'skill' => null,
                    ]);
                }

                $skill->setYamlContent($yamlContent);
                $skill->setSkillId($validator->extractSkillId($yamlContent));
            }

            $this->handleIconUpload($form, $skill);

            $em->persist($skill);
            $em->flush();

            $this->addFlash('success', 'Skill created.');
            return $this->redirectToRoute('app_admin_skill_list');
        }

        return $this->render('admin/skill/form.html.twig', [
            'form' => $form,
            'skill' => null,
        ]);
    }

    #[Route('/skills/{id}/edit', name: 'app_admin_skill_edit')]
    public function edit(
        Skill $skill,
        Request $request,
        EntityManagerInterface $em,
        SkillYamlValidator $validator,
    ): Response {
        $form = $this->createForm(SkillType::class, $skill, ['is_create' => false]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $yamlFile = $form->get('yamlFile')->getData();
            if ($yamlFile) {
                $yamlContent = file_get_contents($yamlFile->getPathname());
                $errors = $validator->validate($yamlContent);

                if (!empty($errors)) {
                    foreach ($errors as $error) {
                        $this->addFlash('error', $error);
                    }
                    return $this->render('admin/skill/form.html.twig', [
                        'form' => $form,
                        'skill' => $skill,
                    ]);
                }

                $skill->setYamlContent($yamlContent);
                $skill->setSkillId($validator->extractSkillId($yamlContent));
            }

            $this->handleIconUpload($form, $skill);

            $em->flush();

            $this->addFlash('success', 'Skill updated.');
            return $this->redirectToRoute('app_admin_skill_list');
        }

        return $this->render('admin/skill/form.html.twig', [
            'form' => $form,
            'skill' => $skill,
        ]);
    }

    #[Route('/skills/{id}/delete', name: 'app_admin_skill_delete', methods: ['POST'])]
    public function delete(
        Skill $skill,
        Request $request,
        EntityManagerInterface $em,
    ): Response {
        if ($this->isCsrfTokenValid('delete' . $skill->getId(), $request->request->get('_token'))) {
            $em->remove($skill);
            $em->flush();
            $this->addFlash('success', 'Skill deleted.');
        }
        return $this->redirectToRoute('app_admin_skill_list');
    }

    #[Route('/skills/{id}/publish', name: 'app_admin_skill_publish', methods: ['POST'])]
    public function publish(Skill $skill, EntityManagerInterface $em): Response
    {
        $skill->setPublishedAt(new \DateTimeImmutable());
        $em->flush();
        $this->addFlash('success', "Skill '{$skill->getSkillId()}' published.");
        return $this->redirectToRoute('app_admin_skill_list');
    }

    #[Route('/skills/{id}/unpublish', name: 'app_admin_skill_unpublish', methods: ['POST'])]
    public function unpublish(Skill $skill, EntityManagerInterface $em): Response
    {
        $skill->setPublishedAt(null);
        $em->flush();
        $this->addFlash('success', "Skill '{$skill->getSkillId()}' unpublished.");
        return $this->redirectToRoute('app_admin_skill_list');
    }

    #[Route('/compile', name: 'app_admin_compile', methods: ['POST'])]
    public function compile(SkillCompiler $compiler): Response
    {
        $compiler->compile();
        $this->addFlash('success', 'Skills compiled successfully.');
        return $this->redirectToRoute('app_admin_skill_list');
    }

    private function handleIconUpload($form, Skill $skill): void
    {
        $iconFile = $form->get('iconFile')->getData();
        if ($iconFile) {
            $iconsDir = $this->getParameter('kernel.project_dir') . '/var/icons';
            if (!is_dir($iconsDir)) {
                mkdir($iconsDir, 0755, true);
            }
            $iconFile->move($iconsDir, $skill->getSkillId() . '.png');
            $skill->setIconPath($iconsDir . '/' . $skill->getSkillId() . '.png');
        }
    }
}
