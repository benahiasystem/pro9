{{--
    Separador de modern-2027.

    Borde punteado: mPDF lo traza sobre el ancho exacto de la celda, asi que el
    separador llega siempre al borde util sin depender del ancho de papel, del
    tamano de fuente ni de contar caracteres.

    El &nbsp; y los width="100%" son necesarios: con la celda vacia mPDF le
    asigna ancho 0 y NO dibuja el borde (comprobado renderizando con mPDF).

    No usar "dashed": mPDF ignora cualquier ajuste del patron y lo dibuja con
    rayas fijas de 2mm y ~1.9mm de hueco (Mpdf::_setDashBorder).

    Va suelto entre tablas, no dentro de otra tabla, para que ocupe todo el ancho.
--}}
<table class="full-width" width="100%">
    <tr>
        <td class="m27-sep" width="100%">&nbsp;</td>
    </tr>
</table>
