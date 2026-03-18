<?php

namespace App\Form;

use App\Entity\Skill;
use App\Form\DataTransformer\TagsTransformer;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;

class SkillType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('yamlFile', FileType::class, [
                'label' => 'YAML File',
                'mapped' => false,
                'required' => $options['is_create'],
                'constraints' => [
                    new File(
                        mimeTypes: ['text/yaml', 'text/plain', 'application/x-yaml', 'application/yaml'],
                        mimeTypesMessage: 'Please upload a valid YAML file',
                    ),
                ],
            ])
            ->add('iconFile', FileType::class, [
                'label' => 'Icon (256x256 PNG)',
                'mapped' => false,
                'required' => false,
                'constraints' => [
                    new File(
                        mimeTypes: ['image/png'],
                        mimeTypesMessage: 'Please upload a PNG image',
                    ),
                ],
            ])
            ->add('tags', TextType::class, [
                'required' => false,
                'attr' => ['placeholder' => 'automotive, smart-home, productivity'],
            ]);

        $builder->get('tags')->addModelTransformer(new TagsTransformer());
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Skill::class,
            'is_create' => true,
        ]);
    }
}
