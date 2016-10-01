require "serverspec"
require "docker"

describe "Dockerfile" do
  before(:all) do
    if ENV['IMAGEID']
      image = Docker::Image.get(ENV['IMAGEID'])
    else
      image = Docker::Image.build_from_dir('.')
    end

    set :os, family: :debian
    set :backend, :docker
    set :docker_image, image.id
  end

  describe port(8983) do
    it "tomcat should be listening" do
      wait_retry 30 do
        should be_listening
      end
    end
  end

  describe command('curl -XGET -I http://localhost:8983/solr/') do
    its(:stdout) { should contain('HTTP/1.1 200 OK') }
  end

  describe command('curl -XGET -I http://localhost:8983/solr/core_de/select/') do
    its(:stdout) { should contain('HTTP/1.1 200 OK') }
  end

  describe command('curl -XGET -I http://localhost:8983/solr/core_en/select/') do
    its(:stdout) { should contain('HTTP/1.1 200 OK') }
  end

  describe command('curl -XGET -I http://localhost:8983/solr/core_ar/select/') do
    its(:stdout) { should contain('HTTP/1.1 200 OK') }
  end

  describe command('curl -XGET -I http://localhost:8983/solr/core_hy/select/') do
    its(:stdout) { should contain('HTTP/1.1 200 OK') }
  end

  describe command('curl -XGET -I http://localhost:8983/solr/core_eu/select/') do
    its(:stdout) { should contain('HTTP/1.1 200 OK') }
  end

  describe command('curl -XGET -I http://localhost:8983/solr/core_bp/select/') do
    its(:stdout) { should contain('HTTP/1.1 200 OK') }
  end

  describe command('curl -XGET -I http://localhost:8983/solr/core_my/select/') do
    its(:stdout) { should contain('HTTP/1.1 200 OK') }
  end

  describe command('curl -XGET -I http://localhost:8983/solr/core_ca/select/') do
    its(:stdout) { should contain('HTTP/1.1 200 OK') }
  end

  describe command('curl -XGET -I http://localhost:8983/solr/core_zh/select/') do
    its(:stdout) { should contain('HTTP/1.1 200 OK') }
  end

  describe command('curl -XGET -I http://localhost:8983/solr/core_cs/select/') do
    its(:stdout) { should contain('HTTP/1.1 200 OK') }
  end

  describe command('curl -XGET -I http://localhost:8983/solr/core_da/select/') do
    its(:stdout) { should contain('HTTP/1.1 200 OK') }
  end

  describe command('curl -XGET -I http://localhost:8983/solr/core_nl/select/') do
    its(:stdout) { should contain('HTTP/1.1 200 OK') }
  end

  describe command('curl -XGET -I http://localhost:8983/solr/core_fi/select/') do
    its(:stdout) { should contain('HTTP/1.1 200 OK') }
  end

  describe command('curl -XGET -I http://localhost:8983/solr/core_fr/select/') do
    its(:stdout) { should contain('HTTP/1.1 200 OK') }
  end

  describe command('curl -XGET -I http://localhost:8983/solr/core_gl/select/') do
    its(:stdout) { should contain('HTTP/1.1 200 OK') }
  end

  describe command('curl -XGET -I http://localhost:8983/solr/core_el/select/') do
    its(:stdout) { should contain('HTTP/1.1 200 OK') }
  end

  describe command('curl -XGET -I http://localhost:8983/solr/core_hi/select/') do
    its(:stdout) { should contain('HTTP/1.1 200 OK') }
  end

  describe command('curl -XGET -I http://localhost:8983/solr/core_hu/select/') do
    its(:stdout) { should contain('HTTP/1.1 200 OK') }
  end

  describe command('curl -XGET -I http://localhost:8983/solr/core_id/select/') do
    its(:stdout) { should contain('HTTP/1.1 200 OK') }
  end

  describe command('curl -XGET -I http://localhost:8983/solr/core_it/select/') do
    its(:stdout) { should contain('HTTP/1.1 200 OK') }
  end

  describe command('curl -XGET -I http://localhost:8983/solr/core_ja/select/') do
    its(:stdout) { should contain('HTTP/1.1 200 OK') }
  end

  describe command('curl -XGET -I http://localhost:8983/solr/core_km/select/') do
    its(:stdout) { should contain('HTTP/1.1 200 OK') }
  end

  describe command('curl -XGET -I http://localhost:8983/solr/core_ko/select/') do
    its(:stdout) { should contain('HTTP/1.1 200 OK') }
  end

  describe command('curl -XGET -I http://localhost:8983/solr/core_lo/select/') do
    its(:stdout) { should contain('HTTP/1.1 200 OK') }
  end

  describe command('curl -XGET -I http://localhost:8983/solr/core_no/select/') do
    its(:stdout) { should contain('HTTP/1.1 200 OK') }
  end

  describe command('curl -XGET -I http://localhost:8983/solr/core_fa/select/') do
    its(:stdout) { should contain('HTTP/1.1 200 OK') }
  end

  describe command('curl -XGET -I http://localhost:8983/solr/core_pl/select/') do
    its(:stdout) { should contain('HTTP/1.1 200 OK') }
  end

  describe command('curl -XGET -I http://localhost:8983/solr/core_pt/select/') do
    its(:stdout) { should contain('HTTP/1.1 200 OK') }
  end

  describe command('curl -XGET -I http://localhost:8983/solr/core_ro/select/') do
    its(:stdout) { should contain('HTTP/1.1 200 OK') }
  end

  describe command('curl -XGET -I http://localhost:8983/solr/core_ru/select/') do
    its(:stdout) { should contain('HTTP/1.1 200 OK') }
  end

  describe command('curl -XGET -I http://localhost:8983/solr/core_es/select/') do
    its(:stdout) { should contain('HTTP/1.1 200 OK') }
  end

  describe command('curl -XGET -I http://localhost:8983/solr/core_sv/select/') do
    its(:stdout) { should contain('HTTP/1.1 200 OK') }
  end

  describe command('curl -XGET -I http://localhost:8983/solr/core_th/select/') do
    its(:stdout) { should contain('HTTP/1.1 200 OK') }
  end

  describe command('curl -XGET -I http://localhost:8983/solr/core_tr/select/') do
    its(:stdout) { should contain('HTTP/1.1 200 OK') }
  end

  describe command('curl -XGET -I http://localhost:8983/solr/core_uk/select/') do
    its(:stdout) { should contain('HTTP/1.1 200 OK') }
  end

end

def wait_retry(time, increment = 1, elapsed_time = 0, &block)
  begin
    yield
  rescue Exception => e
    if elapsed_time >= time
      raise e
    else
      sleep increment
      wait_retry(time, increment, elapsed_time + increment, &block)
    end
  end
end

