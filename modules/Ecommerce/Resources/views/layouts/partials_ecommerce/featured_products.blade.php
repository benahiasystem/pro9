  <h2 class="carousel-title">Featured Products</h2>



  <div class="home-featured-products owl-carousel owl-theme owl-dots-top">

    @php
        $catalogUrls = app(\App\Services\Storage\VersionedImage::class)->catalogUrls($items);
    @endphp
    @foreach ($items as $item)

     <div class="product">
          <figure class="product-image-container">
              <a  href="/ecommerce/item/{{ $item->id }}/{{ \Illuminate\Support\Str::slug($item->description) }}" class="product-image">
{{-- ########## INICIO MIGRACIÓN ALMACENAMIENTO S3 CLOUD-ONPREMISE --}}
                  <x-catalog-image :small="$catalogUrls[$item->image_small] ?? null" :original="$catalogUrls[$item->image] ?? asset('logo/imagen-no-disponible.jpg')" :eager="$loop->index < 4" alt="product" />
{{-- ######### FIN MIGRACIÓN ALMACENAMIENTO S3 CLOUD-ONPREMISE --}}
              </a>
              <a  href="{{route('item_partial', ['id' => $item->id])}}" class="btn-quickview">Quick View</a>
          </figure>
          <div class="product-details">
              <div class="ratings-container">
                  <div class="product-ratings">
                      <span class="ratings" style="width:80%"></span><!-- End .ratings -->
                  </div><!-- End .product-ratings -->
              </div><!-- End .product-container -->
              <h2 class="product-title  tony">
                  <a href="#">{{$item->name}}</a>
              </h2>
              <div class="price-box" @if(!($storefront_show_prices ?? true)) style="display:none" @endif>
                  <span class="product-price">Bs. {{ number_format($item->sale_unit_price, 2) }}</span>
              </div><!-- End .price-box -->

              <div class="product-action">
                  <a href="#" class="paction add-wishlist" title="Add to Wishlist">
                      <span>Add to Wishlist</span>
                  </a>

                  <a href="javascript:void(0)" role="button" data-product="{{ json_encode( $item ) }}" class="paction add-cart" title="Add to Cart">
                      <span>Add to Cart</span>
                  </a>

                  <a href="#" class="paction add-compare" title="Add to Compare">
                      <span>Add to Compare</span>
                  </a>
              </div><!-- End .product-action -->
          </div><!-- End .product-details -->
      </div><!-- End .product -->




    @endforeach






  </div><!-- End .featured-proucts -->
