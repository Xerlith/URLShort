<?php
/**
 * Url entering form.
 */

namespace Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Class UrlForm.
 *
 * @category Epi
 * @package Form
 * @extends AbstractType
 * @use Symfony\Component\Form\AbstractType
 * @use Symfony\Component\Form\FormBuilderInterface
 * @use Symfony\Component\OptionsResolver\OptionsResolverInterface
 * @use Symfony\Component\Validator\Constraints as Assert
 */
class UrlForm extends AbstractType
{
    /**
     * Form builder.
     *
     * @access public
     * @param FormBuilderInterface $builder
     * @param array $options
     *
     * @return FormBuilderInterface
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        return  $builder
            ->add(
                'url_id',
                'hidden'
            )
            ->add(
                'url',
                'text',
                array(
                    'constraints' => array(
                        new Assert\NotBlank(),
                        new Assert\Regex(array(
                            'pattern' => '/^(?!\.\.)((http|ftp|https|sftp)\:\/\/)?[a-zA-Z0-9]+\.[a-zA-Z0-9]+.+$/',
                            'match' => true,
                            'message' => 'This is not a URL address!'))
                    )
                )
            )
            ->add(
                'user_id',
                'hidden'
            )
            ->add(
                'short_url',
                'hidden'
            );
    }

    /**
     * Gets form name.
     *
     * @access public
     *
     * @return string
     */
    public function getName()
    {
        return 'urlForm';
    }
}
