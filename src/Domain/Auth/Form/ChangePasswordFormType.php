<?php

namespace App\Domain\Auth\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

class ChangePasswordFormType extends AbstractType
{
    public function buildForm(
        FormBuilderInterface $builder,
        array $options,
    ): void {
        $builder->add("plainPassword", RepeatedType::class, [
            "type" => PasswordType::class,
            "options" => [
                "attr" => ["autocomplete" => "new-password"],
            ],
            "first_options" => [
                "constraints" => [
                    new NotBlank(
                        message: "password.required",
                    ),
                    new Length(
                        min: 6,
                        minMessage: "password.min_length",
                        // max length allowed by Symfony for security reasons
                        max: 4096,
                    ),
                ],
                "label" => "auth.reset.new_password_label",
            ],
            "second_options" => [
                "label" => "auth.reset.repeat_password_label",
            ],
            "invalid_message" => "password.mismatch",
            // instead of being set onto the object directly,
            // this is read and encoded in the controller
            "mapped" => false,
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([]);
    }
}
