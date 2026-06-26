<?php

namespace App\Domain\Auth\Form;

use App\Domain\Auth\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\IsTrue;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

class RegistrationFormType extends AbstractType
{
    public function buildForm(
        FormBuilderInterface $builder,
        array $options,
    ): void {
        $builder
            ->add("email")
            ->add("agreeTerms", CheckboxType::class, [
                "mapped" => false,
                "constraints" => [
                    new IsTrue(
                        message: "registration.agree_terms",
                    ),
                ],
            ])
            ->add("plainPassword", RepeatedType::class, [
                "type" => PasswordType::class,
                "options" => [
                    "attr" => ["autocomplete" => "new-password"],
                ],
                "first_options" => [
                    "constraints" => [
                        new NotBlank(message: "password.required"),
                        new Length(
                            min: 6,
                            minMessage: "password.min_length",
                            // max length allowed by Symfony for security reasons
                            max: 4096,
                        ),
                    ],
                    "label" => "auth.password_label",
                ],
                "second_options" => [
                    "label" => "auth.register.confirm_password_label",
                ],
                "invalid_message" => "password.mismatch",
                // instead of being set onto the object directly,
                // this is read and encoded in the controller
                "mapped" => false,
            ])
            ->add("locale", ChoiceType::class, [
                "choices" => [
                    "auth.language.fr" => "fr",
                    "auth.language.en" => "en",
                ],
                "label" => "auth.register.language_label",
                "placeholder" => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            "data_class" => User::class,
        ]);
    }
}
