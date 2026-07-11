<?php

namespace App\Domain\Checklist\Form;

use App\Domain\Checklist\Entity\Checklist;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

class ChecklistType extends AbstractType
{
    public function buildForm(
        FormBuilderInterface $builder,
        array $options,
    ): void {
        $builder->add("name", TextType::class, [
            "label" => "checklist.modal.name_label",
            "constraints" => [
                new NotBlank(message: "checklist.validation.name_required"),
                new Length(max: 510),
            ],
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            "data_class" => Checklist::class,
            "csrf_token_id" => "checklist_form",
        ]);
    }
}
